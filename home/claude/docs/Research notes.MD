# Title: Student Note Sharing Portal — Research Notes

Dated facts about backend options, limits, and takedown rules that the debates rely on.

Status: Draft
Last updated: 2026-10-18

Fetched 2026-10-18. Vendor limits change often, so **re-check before committing** (D-P02). Evidence type is noted per row.

## Backend Free-Tier Facts

| Option                        | Fact                                                                                                                                                                 | Evidence                                  |
| ----------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------- |
| Supabase Free                 | 500 MB database, 1 GB file storage, 50 MB max upload, 50,000 MAU, 5 GB egress                                                                                        | Official pricing page                     |
| Supabase Free                 | Projects pause after 1 week of inactivity; max 2 active projects; no automatic backups                                                                               | Official pricing page                     |
| Supabase Pro ($25/mo)         | 8 GB database, 100 GB storage, 250 GB egress, daily backups kept 7 days, no inactivity pause                                                                         | Official pricing page                     |
| Cloudflare R2 free            | 10 GB-month storage, 1M Class A and 10M Class B ops per month, free internet egress, Standard storage only                                                           | Official docs                             |
| Cloudflare D1 free            | 5M rows read/day, 100K rows written/day, 5 GB total storage                                                                                                          | Official docs                             |
| Cloudflare Pages/Workers free | Static requests unlimited; Workers and Pages Functions share 100,000 requests/day; no card to start                                                                  | Third-party comparison, checked Sept 2026 |
| Firebase Spark                | No-cost quotas for Auth (most methods), Firestore, Cloud Storage, Hosting; exceeding a product's quota shuts it off for the month; linking billing upgrades to Blaze | Official docs                             |

**Not verified:** Firebase Cloud Storage numeric quota; whether Cloudflare offers a bundled end-user login service comparable to Supabase Auth (none found in the sources read).

## Astro Integration (Supabase)

Official quickstart: set `output: "server"`, use an adapter (guide uses the Node adapter in standalone mode), `@supabase/supabase-js` plus `@supabase/ssr` for cookie sessions, Astro Actions for sign-up/in/out, email confirmation via a callback that verifies a token. Implication: **a static-only host will not work** for the auth flow.

## Capacity Estimate (my assumption)

**Proposed assumption:** average note about 3 MB. Then 1 GB holds roughly 330 notes; 10 GB roughly 3,300; 100 GB roughly 33,000. Real file sizes are **Open**.

## India Takedown Rules (secondary source, not legal advice)

One law-firm explainer reports: IT Act s.79 safe harbour requires due diligence; a Delhi High Court ruling treats written notice as actual knowledge, with action expected within about 36 hours; Copyright Act s.52(1)(c) describes a 21-day window during which access is withheld unless a court orders otherwise; a complainant must follow up with court action within that window. **Status: Unverified.** Confirm with a qualified lawyer before launch (D-P07).

## Sources

- [Supabase pricing](https://supabase.com/pricing.md)
- [Cloudflare R2 pricing](https://developers.cloudflare.com/r2/pricing/)
- [Cloudflare D1 pricing](https://developers.cloudflare.com/d1/platform/pricing/)
- [Hosting free tiers, checked Sept 2026](https://flaviocopes.com/hosting-free-tiers/)
- [Firebase pricing plans](https://firebase.google.cn/docs/projects/billing/firebase-pricing-plans?hl=en)
- [Supabase Auth with Astro](https://supabase.com/docs/guides/auth/quickstarts/astrojs.md)
- [Takedown notices under Indian law (S.S. Rana)](https://www.ssrana.in/Intellectual%20Property/Copyright/Take-Down-Notice-under-Indian-Law.aspx)
