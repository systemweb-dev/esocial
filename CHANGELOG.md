# Changelog

All Notable changes to sped-esocial will be documented in this file.

Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## Unreleased

### Added
- Apply the S-1.3 XSD package of 2026-10-26 (NT 07/2026 rev. 24/09/2026, item 3.3).
- S-2500: `nmSoc`, `nmCargo`, `nmFuncao`, `CBOFuncao` and `localTrabalho` (`localTrabGeral`, `localTempDom`) in the S-1.3 builder and JSON schema; `tpRegTrab` accepts 3.
- S-2299, S-2399 and S-2420: optional `indCPFDesvinc` (only value 9) in the S-1.3 builder and JSON schema.
- `Common\SystemMessages`: official catalog of the eSocial System Messages v2.5 (`data/mensagens-sistema.json`), with text and suggested actions by code (`get(191)`, `get('MS0191')`, suffixed variants).
- S-1000 (S-1.3): optional `indPertIRRF` (`S`), already in the XSD and missing from the builder.

### Fixed
- S-1005: emit `fimValid` in `inclusao/ideEstab` too (allowed by `T_ideEstab` in every S-1.x layout).
- S-1020: build the `dadosOpPort` group (`aliqRat`, `fap`), accepted by the JSON schema but never emitted.
- S-1200 (S-1.3): read `observacao` from `infoComplem/sucessaoVinc` instead of `infoComplem`.
- S-2501: build `calcTrib`/`infoCRContrib` (the block was empty) and emit one `ideTrab` per worker (only the last one was appended).
- S-2501 JSON schema: validate `tpCR` of `infoCRContrib` (`"pattewrn"` typo) and move `ideAdv` from `despProcJud` to `infoRRA`, as in the XSD.
- Examples: drop `dadosOpPort` from the S-1020 example (only for `tpLotacao` 08) and fix keys `tpacidtransito` (S-2230), `matricant` (S-2300), `tpinsc`/`nrinsc` in `ideAdv` (S-2501); remove obsolete `dsctpjorn` (S-2206).
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
