# Changelog

All Notable changes to sped-esocial will be documented in this file.

Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## Unreleased

### Added
- Apply the S-1.3 XSD package of 2026-10-26 (NT 07/2026 rev. 24/09/2026, item 3.3).
- S-2500: `nmSoc`, `nmCargo`, `nmFuncao`, `CBOFuncao` and `localTrabalho` (`localTrabGeral`, `localTempDom`) in the S-1.3 builder and JSON schema; `tpRegTrab` accepts 3.
- S-2299, S-2399 and S-2420: optional `indCPFDesvinc` (only value 9) in the S-1.3 builder and JSON schema.

### Fixed
- Align S-1.3 TSV JSON schemas with optional internship level and institution fields in the XSD (#592).
- Omit absent `nivEstagio` when generating TSV XML through the shared S-1.2/S-1.3 builders.

## 0.1.0-dev - 2016-04-01

### Added
- Nothing

### Deprecated
- Nothing

### Fixed
- Nothing

### Removed
- Nothing

### Security
- Nothing
