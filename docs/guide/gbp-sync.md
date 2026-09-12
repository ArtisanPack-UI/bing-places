---
title: Sync from Google Business Profile
---

# Sync from Google Business Profile

Bing Places for Business ships a first-party **"Sync from Google
Business Profile"** import that pulls a business's location data,
hours, categories, and photos directly from an existing Google Business
Profile listing. It runs entirely in the Bing Places UI and does not
require management-API access.

For most listings this is the recommended way to get onto Bing. This
package still ships the management-API client for the incremental
writes access approval unlocks (see the
[restricted-access guide](restricted-access.md)), but for the first-time
publication the sync path is faster and self-service whenever an
eligible GBP listing already exists — see
[When to use the sync path](#when-to-use-the-sync-path) for the
eligibility rule and
[When to reach for the management API instead](#when-to-reach-for-the-management-api-instead)
for the cases it does not cover.

## When to use the sync path

Reach for the GBP-sync path when:

- **The business is already published to Google Business Profile.**
  Bing pulls from GBP directly; no other source is required.
- **Management-API access has not been granted (yet).** This is the
  common case — see the [restricted-access guide](restricted-access.md).
  Sync gets the listing live on Bing today; the management-API client
  can pick up incremental writes once access lands.
- **The volume is one listing, or a small number of listings.** The
  sync is a UI operation. It scales for a handful of businesses per
  Bing Places account, not for scripted mass onboarding.

## When to reach for the management API instead

Use the management API — or plan to, the day access is granted —
when:

- **The business does not have a Google Business Profile listing.**
  There is nothing for Bing to sync from.
- **Bing needs data GBP does not have (or has differently).** Chains
  that maintain Bing-specific attributes, or businesses whose Bing
  presence intentionally diverges from GBP, cannot rely on sync.
- **The workflow requires scripted / programmatic writes.** Sync is a
  UI action; the management API is what makes updates automatable.
- **Reviews, local posts, or media are being managed as an ongoing
  workflow.** Sync brings a listing into being; the management API is
  how it is maintained.

## Recommended workflow for Keystone CMS

For the Keystone CMS integration:

1. **Onboard by syncing from GBP.** When a business connects its
   Google Business Profile in Keystone, prompt the operator to run
   Bing's sync in the Bing Places UI. This makes the listing live on
   Bing without waiting on management-API access.
2. **Let the management-API client sit idle** until Microsoft grants
   access. The client, DTOs, and fixtures are already in place; the
   `TokenProvider` is stubbed for tests. Nothing in production tries
   to call Bing over the management API yet.
3. **When access is granted, switch on the incremental writes.** Wire
   the real `TokenProvider` binding, remove the `Http::fake()` in the
   integration's production path, and start pushing the writes
   (review replies, local posts, media uploads, targeted updates) that
   the sync path cannot cover.

This ordering means the Bing side of a Local SEO integration ships in
step 1 — well before Microsoft grants management-API access — and the
richer management surface is unlocked as a follow-up when it does.

## What the sync path does not cover

The Bing Places GBP sync is a snapshot import. It does not:

- Replay ongoing GBP changes into Bing automatically. Subsequent GBP
  edits do not propagate to the Bing listing without either a manual
  re-sync in the Bing UI or a management-API write from this package.
- Cover review replies. Reviews on Bing are managed on the Bing
  listing; syncing from GBP does not import GBP replies or set up an
  ongoing reply channel.
- Cover local posts, offers, or media that live only on the Bing side.
  Anything published to Bing outside of what GBP holds is a
  management-API job.

For all of those, the management API — and this package — is the
long-term path. The sync is the day-one path.

See also: [Restricted-access reality](restricted-access.md) for what
management-API access actually gates.
