# Title: Student Note Sharing Portal — Report Outline

Coursework report structure. The narrative draft lives in `report/REPORT.md`;
figures come from `screenshots/` per [screenshots.md](screenshots.md).

| # | Section                              | Content source                                                      |
| - | ------------------------------------ | --------------------------------------------------------------------- |
| 1 | Title page                           | team members, course, supervisor — owner fills                        |
| 2 | Abstract                             | report draft (one paragraph: problem, method, result)                 |
| 3 | Introduction & Problem Statement     | prd.md §Problem + §Target users                                       |
| 4 | Objectives & Scope                   | prd.md FR table + out-of-scope list                                   |
| 5 | System analysis                      | user-journeys.md + decision-debates summary (historical)               |
| 6 | Design — architecture                | architecture.md diagram + route matrix                                |
| 7 | Design — database                    | backend-schema.md table map + ER diagram (report includes mermaid + rendered image) |
| 8 | Implementation — modules             | PROJECT-structure walkthrough: 8 screens + roles                      |
| 9 | Implementation — differentiators     | D-012 four modules, one subsection each                               |
| 10| Security & privacy                   | security.md controls table + threat-model residual risks              |
| 11| Testing                              | testing.md #01–15 results + test table                                |
| 12| Screenshots & user flows             | screenshots.md set, numbered as figures                               |
| 13| Installation guide                   | deployment.md Option A (XAMPP)                                        |
| 14| Limitations & future work            | threat model residual rows; D-P07/P09/P10/P11                          |
| 15| Conclusion                           | objectives met (FR table ticks)                                       |
| 16| References                           | PHP manual, MySQL 8 manual, OWASP cheat sheets                        |

## Presentation tips baked into REPORT.md

- One figure per module minimum (12+ figures), each followed by 1–3 sentences.
- Show the appendix: role capability matrix instead of dumping code.
- Keep the honest "residual risks" list — graders value it.
- Prefer tables over paragraph walls; the doc set is written table-first on purpose.
