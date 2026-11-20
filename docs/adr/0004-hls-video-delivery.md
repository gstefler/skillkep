# ADR-0004: Protected HLS video delivery

- Status: Accepted (not built yet)
- Date: 2026-11-20

## Context

Course videos must only be watchable by learners assigned to the course, but serving every segment through PHP would be slow and expensive.

## Decision

- Uploaded videos are transcoded to HLS by a queued ffmpeg job (Horizon `video` supervisor).
- Each video is encrypted with AES-128 and gets its own key. Key rotation may come later.
- Laravel serves the playlist and the key, and only after it checks that the user has the course assigned.
- The encrypted segments are served statically by Caddy. There is no per-segment authorisation.

## Consequences

- Segment delivery is cheap and cacheable; access control lives in the playlist and key endpoints.
- Someone who obtained a key and the segments could still play the video. This is accepted: the goal is to stop casual sharing, not to build DRM.
- Rotating keys later means re-encrypting or re-segmenting existing videos.
