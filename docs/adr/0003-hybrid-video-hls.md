# ADR 0003 — Hybrid video: chunked uploads + HLS, or YouTube/Vimeo embeds

- **Status:** accepted · 2026-11-02

## Context

Lessons need video. The constraints are free tools only, a single VPS, and Cloudflare's free plan, which caps request bodies at 100 MB. Premium lessons must not be freely downloadable.

## Decision

- **Embeds:** paste a YouTube or Vimeo URL. It is validated through the providers' public oEmbed endpoints (no API keys) and rendered with `youtube-nocookie.com` behind a click-to-load facade.
- **Uploads:** a home-made resumable chunk protocol. Raw 8 MB `PUT` bodies are size-verified and retried. This avoids third-party upload servers and stays far below Cloudflare's limit.
- **Processing:** a queued `ProcessUploadedVideo` job (`media` queue) uses ffprobe/ffmpeg to produce a thumbnail and adaptive HLS. Renditions go up to 720p and never above the source height. Frames are forced to `yuv420p` for browser compatibility. The original is deleted afterwards.
- **Delivery:** every playlist and segment request goes through `LessonPolicy::watch`. In production Laravel only replies with `X-Accel-Redirect` and Nginx streams the file. hls.js plays it, and Safari uses native HLS.
- Status updates use Inertia polling (`usePoll`) instead of WebSockets, to keep the stack small.

## Consequences

- Works on one VPS with no paid services. Storage can move to Backblaze B2 later.
- Per-segment authorization costs one cheap request to PHP per segment, which is acceptable at this scale.
- Queue `retry_after` must stay above the job timeout. A test guards this, because a lower value would let two workers transcode the same video.
