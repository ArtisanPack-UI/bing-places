---
title: Restricted-Access Reality
---

# Restricted-Access Reality

Access to the Bing Places for Business **management API** is not
self-service. Microsoft grants it through its agency / partner program,
after a manual review of the requesting organisation. This page
captures what that means for the package: what the gate actually
blocks, what it does not, and how development stays unblocked while
access is pending.

## What is gated

- **All live management-API calls.** Every write (create / update /
  delete a business, reply to a review, publish media) and every read
  through the management API requires a Bing Places account whose
  management-API access has been approved by Microsoft. Without
  approval, Bing Places returns `403` — the Trusted-Partner check
  Microsoft documents for missing credentials, a missing client
  certificate, or an account not enrolled in the partner program. The
  client surfaces every non-2xx response (including that `403`) as
  `ApiException`. `401` is a separate case that means the OAuth token
  itself was rejected — see [Common failure modes](#common-failure-modes)
  below.
- **Bulk / programmatic edits at any real volume.** The whole reason to
  use this package rather than the Bing Places UI is scripted, repeated
  updates. That capability is gated behind access approval.

## What is not gated

- **Development against `Http::fake()`.** The fake factory never leaves
  the process, so approval is irrelevant. The whole test suite for this
  package runs unapproved. See the [testing guide](testing.md).
- **Minting Microsoft OAuth access tokens.** Tokens can be issued
  before Bing Places access is approved; the `403` comes back from the
  Bing Places API surface itself, not from the OAuth flow.
- **The Bing Places UI itself.** Anyone can create and manage listings
  through Microsoft's Bing Places web UI without management-API access.
  That's the path the [GBP-sync guide](gbp-sync.md) covers.
- **Publishing to Bing via the GBP sync path.** Bing Places' first-party
  **"Sync from Google Business Profile"** import runs in the Bing Places
  UI and does not require management-API access. For most listings
  that's the recommended way to get on Bing.

## Common failure modes

- **`403 Bing Places authorization failed`** — the Trusted-Partner check
  refused the request. Bing Places documents `403` for missing
  credentials, a missing client certificate, or an account that is not
  enrolled in the partner program. It also fires when the OAuth token
  is valid but lacks the scope the API requires. Both are the same
  status code with different underlying causes; the client cannot
  distinguish them and neither can the token flow. Treat a persistent
  `403` as the signal that partner access (or the required scope) is
  not yet in place, and confirm through Microsoft's partner support
  channel rather than iterating on the client.
- **`401 Bing Places authentication failed`** — Microsoft rejected the
  OAuth token itself: expired, revoked, malformed, or issued for a
  different tenant. Refresh the token via the provider and retry once;
  a repeat `401` means the underlying credential is bad, not that
  partner access is missing.

## Requesting access

Microsoft does not publish a public URL for a general "management API
access" form the way Google does for Google Business Profile. The
current path is:

1. Enrol in Microsoft's agency / partner program for Bing Places (the
   entry point moves between Microsoft's advertising and business
   properties; check the [Bing Places for Business site][bpb] for the
   current link).
2. Submit the API access request via the program's intake channel,
   including the intended use case, the list of Bing Places account ids
   the access should cover, and the organisation vetting Microsoft
   asks for.
3. Wait. Turnaround is unpredictable — days to weeks — and Microsoft
   may come back asking for clarification.

Access is per Bing Places account (and, in some approval flows, per
requesting organisation). If accounts are added later, or the
requesting organisation changes, the access may need to be re-requested.

[bpb]: https://www.bing.com/forbusiness

## Keeping development unblocked

While access is pending:

- Write and test all client code against `Http::fake()` — see the
  [testing guide](testing.md).
- Wire up the [`TokenProvider`](token-provider.md) binding against a
  stub. No real Microsoft token is needed to develop the client.
- Capture representative response payloads from Microsoft's public API
  documentation and inline them into the fakes as fixtures.
- For any listing that is already published to Google Business Profile,
  use Bing's [GBP-sync path](gbp-sync.md) to keep the Bing listing in
  parity in the meantime. The management-API integration can come
  online later without breaking anything the sync produced.

Once access lands, swap the stub `TokenProvider` for the real
MicrosoftOAuth-backed one and remove the `Http::fake()` call. No client
code has to change.

See also: [Token provider contract](token-provider.md) and
[Testing with `Http::fake()`](testing.md).
