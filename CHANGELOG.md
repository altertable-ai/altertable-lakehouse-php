# Changelog

## 0.1.0 (2026-05-14)

- Initial PHP client library for the Altertable Lakehouse API
- Full endpoint coverage: `append`, `query`, `queryAll`, `getQuery`, `cancelQuery`, `upload`, `validate`
- Typed models with PHP 8.1 enums (`ComputeSize`, `UploadFormat`, `UploadMode`)
- NDJSON streaming for `query` with row iterator
- Configurable auth support: direct credentials, pre-encoded tokens, and environment variable discovery
- Typed error hierarchy with retriable classification
- Configurable timeouts, retries with exponential backoff, and connection keep-alive via Guzzle
- Unit tests with mocked HTTP client
- Integration tests against `altertable-mock`
- CI pipeline with lint + unit + integration tests
