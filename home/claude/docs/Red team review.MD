# Title: Student Note Sharing Portal — Red Team Review

An attacker-versus-defender pass over the proposed design, with a test to write for each attack.

Status: Proposed
Last updated: 2026-10-18

Design-level review only: no code exists, so no attack here is confirmed. Findings T-08 to T-11 are added to [threat-model.md](threat-model.md); this file records the argument.

| ID   | Attacker says                                                                   | Defender answers (Proposed control)                                                                            | Residual risk                                 | Test to write                                              |
| ---- | ------------------------------------------------------------------------------- | -------------------------------------------------------------------------------------------------------------- | --------------------------------------------- | ---------------------------------------------------------- |
| T-02 | Upload an SVG or HTML file with script, or a PDF with embedded script           | Allow-list types; never render uploads inline; serve with `nosniff` and download disposition; scan if feasible | Medium until scanning is chosen               | Upload hostile samples; assert rejection or safe headers   |
| T-08 | Guess the file URL of a _Pending_ or _Rejected_ note in a public storage bucket | Keep unpublished files in a private bucket; serve through short-lived signed links                             | Low if enforced                               | Request a pending file URL anonymously; expect 403         |
| T-09 | Use sign-up and login error messages to learn which emails are registered       | Identical responses for known and unknown emails; rate limits                                                  | Low                                           | Compare responses for both cases                           |
| T-10 | File a report, then watch which note vanishes and when, to unmask the reporter  | Hide reporter identity everywhere; delay or batch public log entries; log reason category only                 | Medium: timing leaks are hard to remove fully | Check the transparency view for reporter fields and timing |
| T-11 | Name a moderator in the public log, then harass them                            | Show actor role, not name, in user-facing views                                                                | Low                                           | Inspect the transparency API payload                       |
| T-01 | Read admin-only fields through the transparency endpoint                        | Field allow-list at the server, not the UI                                                                     | Low if tested                                 | Diff the endpoint output against the allow-list            |
| T-03 | Call moderation or admin endpoints as a student                                 | Server-side role checks on every request                                                                       | Low if tested                                 | Per-role denial tests                                      |
| T-06 | Phish a moderator, then bulk-remove notes                                       | Strong sign-in, short sessions, audit log, admin-only role changes                                             | Medium                                        | Review audit log entries on role changes                   |
| T-04 | Mass-create accounts and upload spam                                            | Verified institution email, rate limits, pre-moderation                                                        | Medium                                        | Scripted burst test on preview only                        |

## Defender's Own Weak Spots

1. Scanning has no chosen tool, so T-02 stays open ([technology.md](technology.md)).
2. Timing leaks (T-10) can be reduced, not eliminated.
3. Every control above is only a plan until implemented and tested ([testing.md](testing.md)).

## Verdict (Proposed)

Highest priority before any real students join: T-08, T-01, T-03, T-02.
