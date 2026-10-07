#!/usr/bin/env python3
"""Checks a demo site the way the app and App Review will use it.

    python3 scripts/check.py https://hivelog-demo.ddev.site            # read-only
    python3 scripts/check.py https://demo.example.org --write          # also records one inspection with a photo

It signs in as the reviewer through the real OAuth flow (login form, consent screen, code and PKCE
verifier for a token), then reads what the app reads and checks the seeded data is what the review
notes promise. With --write it also creates an inspection as plain JSON and attaches a photo as a
multipart form (the two forms that get through a host firewall), which `ddev demo-reset` removes.
The reviewer's password comes from DEMO_REVIEWER_PASSWORD or `.demo-secrets` next to this folder.
Add --insecure for a local DDEV site whose certificate Python does not trust, and --print-token to
print the tokens as `export` lines for the app's smoke test. Standard library only.
"""
import base64
import hashlib
import html
import http.cookiejar
import json
import os
import re
import ssl
import sys
import urllib.error
import urllib.parse
import urllib.request
import uuid
from pathlib import Path

args = [a for a in sys.argv[1:] if not a.startswith("--")]
flags = {a for a in sys.argv[1:] if a.startswith("--")}
if not args:
    sys.exit(__doc__)
base = args[0].rstrip("/")
UA = "Vinculum/1 CFNetwork/3896 Darwin/27"  # an app-like agent: some hosts refuse curl's

secrets = {}
secrets_file = Path(__file__).resolve().parent.parent / ".demo-secrets"
if secrets_file.exists():
    for line in secrets_file.read_text().splitlines():
        if "=" in line:
            k, v = line.split("=", 1)
            secrets[k] = v
user = os.environ.get("DEMO_REVIEWER_USER", secrets.get("DEMO_REVIEWER_USER", "reviewer"))
password = os.environ.get("DEMO_REVIEWER_PASSWORD", secrets.get("DEMO_REVIEWER_PASSWORD"))
if not password:
    sys.exit("No reviewer password: set DEMO_REVIEWER_PASSWORD or run from a folder with .demo-secrets.")

context = ssl.create_default_context()
if "--insecure" in flags:
    context.check_hostname = False
    context.verify_mode = ssl.CERT_NONE
jar = http.cookiejar.CookieJar()


class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, *a, **k):
        return None


def opener(follow=True):
    handlers = [urllib.request.HTTPCookieProcessor(jar), urllib.request.HTTPSHandler(context=context)]
    if not follow:
        handlers.append(NoRedirect())
    return urllib.request.build_opener(*handlers)


def request(url, data=None, headers=None, method=None, follow=True):
    req = urllib.request.Request(url, data=data, method=method, headers={"User-Agent": UA, **(headers or {})})
    try:
        r = opener(follow).open(req, timeout=60)
        return r.status, r.read(), dict(r.headers)
    except urllib.error.HTTPError as e:
        return e.code, e.read(), dict(e.headers)


failures = []


def check(ok, what):
    print(("  ok    " if ok else "  FAIL  ") + what)
    if not ok:
        failures.append(what)


print(f"== {base}")
status, body, _ = request(f"{base}/hivelog/api/v1", headers={"Accept": "application/json"})
check(status == 200, "the discovery document answers")
meta = json.loads(body)["meta"]["hivelog_api"] if status == 200 else {}
check(meta.get("api_version") == 1, f"API version 1 (module {meta.get('module_version')})")
print(f"        features: {meta.get('features')}")

# Sign in: the login form, then the consent screen, then the code for a token.
status, page, _ = request(f"{base}/user/login")
build_id = re.search(r'name="form_build_id" value="([^"]*)"', page.decode()).group(1)
form = urllib.parse.urlencode({"name": user, "pass": password, "form_build_id": build_id, "form_id": "user_login_form", "op": "Log in"})
request(f"{base}/user/login", data=form.encode(), headers={"Content-Type": "application/x-www-form-urlencoded"}, follow=False)

verifier = base64.urlsafe_b64encode(os.urandom(32)).rstrip(b"=").decode()
challenge = base64.urlsafe_b64encode(hashlib.sha256(verifier.encode()).digest()).rstrip(b"=").decode()
query = urllib.parse.urlencode({
    "client_id": meta.get("oauth", {}).get("client_id", "hivelog-ios"), "response_type": "code",
    "redirect_uri": "hivelog://oauth/callback", "scope": "hivelog_field_app", "state": "check",
    "code_challenge": challenge, "code_challenge_method": "S256",
})
status, page, headers = request(f"{base}/oauth/authorize?{query}", follow=False)
if status == 200:  # first time for this user: the consent screen
    text = page.decode()
    action = html.unescape(re.search(r'<form[^>]*simple-oauth-authorize-form[^>]*action="([^"]*)"', text).group(1))
    fields = dict(re.findall(r'<input[^>]*type="hidden"[^>]*name="([^"]*)"[^>]*value="([^"]*)"', text))
    check("Vinculum" in text, "the consent screen names the app")
    consent = urllib.parse.urlencode({**fields, "op": "Allow"})
    status, page, headers = request(base + action, data=consent.encode(), headers={"Content-Type": "application/x-www-form-urlencoded"}, follow=False)
location = headers.get("Location", "")
code = urllib.parse.parse_qs(urllib.parse.urlparse(location).query).get("code", [""])[0]
check(location.startswith("hivelog://oauth/callback") and bool(code), "signing in returns to the app's address with a code")
status, body, _ = request(f"{base}/oauth/token", data=urllib.parse.urlencode({
    "grant_type": "authorization_code", "client_id": "hivelog-ios", "code": code,
    "code_verifier": verifier, "redirect_uri": "hivelog://oauth/callback"}).encode(),
    headers={"Content-Type": "application/x-www-form-urlencoded"})
check(status == 200, "the code is exchanged for a token")
tokens = json.loads(body) if status == 200 else sys.exit("No token; cannot continue.")
token = tokens["access_token"]
if "--print-token" in flags:  # for the app's smoke test: eval "$(... --print-token | grep ^export)"
    print(f"export VINCULUM_SMOKE_ACCESS={token}\nexport VINCULUM_SMOKE_REFRESH={tokens['refresh_token']}")
# The app's API calls carry the bearer token and no cookie. With the login session cookie as well,
# Drupal authenticates by cookie and then wants a CSRF token on a write.
jar.clear()
auth = {"Authorization": f"Bearer {token}", "Accept": "application/vnd.api+json"}


def get(path):
    status, body, _ = request(f"{base}/hivelog/api/v1/{path}", headers=auth)
    return status, (json.loads(body) if body else {})


def records(path):
    status, doc = get(path + ("&" if "?" in path else "?") + "page%5Blimit%5D=50")
    return doc.get("data", []) if status == 200 else []


apiaries = records("apiary/apiary")
hives = records("hive/hive")
queens = records("queen/queen")
inspections = records("hive_inspection/hive_inspection?include=images")
check(len(apiaries) == 1, f"the reviewer sees 1 apiary ({len(apiaries)})")
check(len(hives) == 3, f"with 3 hives ({len(hives)})")
check(len(queens) >= 3, f"queens ({len(queens)})")
check(len(inspections) >= 10, f"inspections ({len(inspections)})")
with_photos = [i for i in inspections if i.get("relationships", {}).get("images", {}).get("data")]
check(len(with_photos) >= 2, f"inspections with photos ({len(with_photos)})")

status, alerts = get("computed/alerts")
count = alerts.get("meta", {}).get("count", 0) if status == 200 else 0
check(status == 200 and 1 <= count <= 20, f"a handful of alerts for the Alerts tab ({count})")
status, schema = get("schema")
check(status == 200 and "hive_inspection" in json.dumps(schema), "the field schema answers")
first_hive = hives[0]["id"] if hives else None
status, tiles = get(f"computed/hive/{first_hive}/stat-tiles") if first_hive else (0, {})
check(status == 200, "a hive page's stat tiles answer")
sensor_tiles = [t for t in (tiles.get("data", []) if status == 200 else []) if t.get("key", "").startswith("nanoprobe_")]
check(len(sensor_tiles) >= 2, f"the hive page has its sensor tiles (the app's role may view the user's own sensors) ({len(sensor_tiles)})")
apiary_id = apiaries[0]["id"] if apiaries else None
status, verdicts = get(f"computed/apiary/{apiary_id}/hive-insights") if apiary_id else (0, {})
rows = verdicts.get("data", []) if status == 200 else []
check(status == 200 and len(rows) == 3, f"each hive has an insight verdict, in one request, for its badge ({len(rows)})")
check(sum(1 for r in rows if r.get("stale")) == 1 and {"inspect_soon", "all_clear"} <= {r.get("verdict") for r in rows},
      "the verdicts include inspect soon, all clear and one out of date")

if "--write" in flags and first_hive:
    new_id = str(uuid.uuid4())
    doc = {"data": {"type": "hive_inspection--hive_inspection", "id": new_id,
                    "attributes": {"inspection_date": "2026-01-01", "notes": "Written by scripts/check.py"},
                    "relationships": {"hive": {"data": {"type": "hive--hive", "id": first_hive}}}}}
    status, body, _ = request(f"{base}/hivelog/api/v1/hive_inspection/hive_inspection", data=json.dumps(doc).encode(),
                              headers={**auth, "Content-Type": "application/json"}, method="POST")
    check(status == 201, f"an inspection is recorded as plain JSON ({status})")
    jpeg = base64.b64decode("/9j/4AAQSkZJRgABAQEASABIAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////wgALCAABAAEBAREA/8QAFBABAAAAAAAAAAAAAAAAAAAAAP/aAAgBAQABPxA=")
    boundary = "check" + uuid.uuid4().hex
    part = (f"--{boundary}\r\nContent-Disposition: form-data; name=\"file\"; filename=\"check.jpg\"\r\n"
            f"Content-Type: image/jpeg\r\n\r\n").encode() + jpeg + f"\r\n--{boundary}--\r\n".encode()
    status, body, _ = request(f"{base}/hivelog/api/v1/hive_inspection/hive_inspection/{new_id}/images", data=part,
                              headers={**auth, "Content-Type": f"multipart/form-data; boundary={boundary}"}, method="POST")
    check(status == 200, f"a photo is attached as a multipart form ({status})")
    print("        (the test inspection stays until the next `ddev demo-reset`)")

print()
print("All checks passed." if not failures else f"{len(failures)} check(s) failed.")
sys.exit(1 if failures else 0)
