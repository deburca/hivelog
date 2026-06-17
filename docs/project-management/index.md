---
title: Hivelog Dashboard
type: dashboard
tags: [hivelog/dashboard]
updated: 2026-06-16
---
# 🐝 Hivelog — Dashboard

Map-of-content for the Hivelog module vault. The tables below are
[Dataview](https://github.com/blacksmithgu/obsidian-dataview) queries; they
populate automatically from note frontmatter. If you don't use Dataview, the
static links in [[README]] and the folders still work fine.

## 🔥 Active work
```dataview
TABLE status, priority, project, file.mtime AS "updated"
FROM #hivelog/task
WHERE status = "in-progress" OR status = "review"
SORT priority asc, file.mtime desc
```

## 🧊 Backlog & todo
```dataview
TABLE status, priority, project
FROM #hivelog/task
WHERE status = "todo" OR status = "backlog"
SORT priority asc, file.name asc
```

## 🚧 Blocked
```dataview
TABLE blocked-by AS "blocked by", project
FROM #hivelog/task
WHERE status = "blocked"
```

## 📦 Projects
```dataview
TABLE status, target
FROM #hivelog/project
SORT status asc
```

## 🧭 Decisions (ADRs)
```dataview
TABLE status, date
FROM #hivelog/decision
SORT file.name asc
```

## ✅ Recently shipped
```dataview
TABLE status, project
FROM #hivelog/task
WHERE status = "done"
SORT file.mtime desc
LIMIT 10
```

---
## Quick links
- New work? Copy a template from `templates/` → [[task]], [[project]],
  [[decision]], [[release]].
- Roadmap: [[roadmap]] — release timeline, decision gate, and critical path
- Current focus project: [[queen-observation-enhancements]]
- Module overview lives in the repo's `README.md` and `AGENTS.md` at the repo
  root.
