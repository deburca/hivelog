---
type: decision
tags:
  - hivelog/decision
status: accepted
date: 2026-06-17
supersedes:
---
# ADR-0011: Responsive design strategy & breakpoints

## Status
accepted

## Context
The module ships no responsive CSS — a repo-wide search for `@media` returns
nothing — so every page is implicitly desktop-only. Styling is split between
plain CSS (`css/*.css`) and Tailwind/DaisyUI utilities inside SDC `.twig` files.
A single strategy is needed before the per-page mobile work begins.

## Decision (recommended)
Use plain-CSS `@media` queries in module-owned `css/*.css` as the primary
responsive mechanism (framework-independent, no theme build required), with a
shared breakpoint set: **≤480px** phone, **≤768px** small tablet, **>768px**
desktop (optionally a 1024px wide tier). Tailwind responsive utilities
(`sm:`/`md:`) may be used only inside components already built that way. Shared
rules live in a new `css/hivelog.responsive.css` (library `hivelog/responsive`),
which the other module libraries depend on for one breakpoint vocabulary;
component-specific rules live in per-file `@media` blocks in each component's
CSS file. (Settled by [[0004-responsive-foundation-and-breakpoints]].)

## Consequences
- Positive: predictable, theme-independent responsiveness; one breakpoint
  vocabulary across the module.
- Negative / trade-offs: utility-driven components are handled separately;
  two mechanisms still coexist.
- Follow-up tasks: implemented by [[0004-responsive-foundation-and-breakpoints]]
  and consumed across the [[mobile-ux-improvements]] project.
