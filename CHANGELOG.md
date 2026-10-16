# Changelog

All Notable changes to sped-esocial will be documented in this file.

Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## Unreleased

## 2.0.1 - 2026-10-16

### Fixed
- S-1270: `indGuia` is optional (`minOccurs="0"` in the XSD); without it the builder wrote an empty `<indGuia/>` and the XML was rejected.
- S-2416: `dscSuspensao` was read from the misspelled key `dscssuspensao` and never reached the XML.
- S-1299: `evtComProd` (required by the XSD) is declared in the JSON schema; before, a missing value only failed in the XSD validation.
- JSON schemas of S-2416 (`dscSuspensao`), S-2210 (`dscCompLesao`) and S-2299 (`infoInterm`) declared mixed-case keys, so their rules were never applied (the input is lowercased before validation). A test now requires lowercase keys in every schema.

## 2.0.0 - 2026-10-14

First release of the System Web fork. Breaking changes from the upstream 1.0.x API are listed
under Changed and Removed.

### Changed
- Package renamed to `systemweb-dev/esocial` (fork of `nfephp-org/sped-esocial`); namespace `NFePHP\eSocial` and class names are unchanged.
- `EventsException` keeps the error code in `getCode()`; codes 1004 and 1005 were removed.
- TLS of the eSocial webservices is verified by default (system CA store; the hosts use the public GlobalSign Root R46). `loadCA()` replaces the store and throws if the file does not exist; `disableSecurity(true)` is the explicit opt-out.
- The certificate and key go to cURL in memory (`CURLOPT_SSLCERT_BLOB`/`CURLOPT_SSLKEY_BLOB`); no PEM is written to disk.
- Only the S-1.3 layout: `eventoVersion` below 1.3 throws `InvalidArgumentException` in the event constructor.
- Batch groups follow the MOS S-1.3 (1 = tables, 2 = non-periodic, 3 = periodic); the group may be an int or a numeric string; an invalid group throws `InvalidArgumentException`.
- Event Ids no longer repeat in the same process; `sequencial` must be an integer from 1 to 99999.
- Communication package 1.6-Alfa (`serviceVersion '1.6.0'`, alphanumeric CNPJ) is the recommended one.
- PHP >= 8.1; optional typed parameters are explicitly nullable.
- `Tools::EVT_INICIAIS` is deprecated in favour of `Tools::EVT_TABELAS`.

### Removed
- Builders of events the library cannot send (extinct in S-1.x, eSocial returns S-5xxx, Judiciary S-8200/S-8299): calling them throws `EventsException` code 1003 with the reason.
- Layouts S-1.0, S-1.1 and S-1.2 (schemes, JSON schemas and examples).
- `ParamsStandardize`, `Sanitize`, `SoapCode`, the `console/` scripts and dead `Factory`/`SoapBase` methods.
- Per-event docs of the old layouts (`docs/Evt*.md`, `EVENTOS_S_1_*.md`), replaced by the generated `docs/eventos/`.

### Added
- Apply the S-1.3 XSD package of 2026-10-26 (NT 07/2026 rev. 24/09/2026, item 3.3).
- S-2500: `nmSoc`, `nmCargo`, `nmFuncao`, `CBOFuncao` and `localTrabalho` (`localTrabGeral`, `localTempDom`) in the S-1.3 builder and JSON schema; `tpRegTrab` accepts 3.
- S-2299, S-2399 and S-2420: optional `indCPFDesvinc` (only value 9) in the S-1.3 builder and JSON schema.
- `Common\SystemMessages`: official catalog of the eSocial System Messages v2.5 (`data/mensagens-sistema.json`), with text and suggested actions by code (`get(191)`, `get('MS0191')`, suffixed variants).
- S-1000 (S-1.3): optional `indPertIRRF` (`S`), already in the XSD and missing from the builder.
- Generated per-event docs in `docs/eventos/` (`gen_docs.py`) and the docs checker `check_docs.py`; README usage guide, `docs/Tools.md` and `docs/Config.md` rewritten.

### Fixed
- `XsdSeeker`: every lookup starts from a clean list (a `Tools` created after another with a different communication package inherited its files).
- S-1005: emit `fimValid` in `inclusao/ideEstab` too (allowed by `T_ideEstab` in every S-1.x layout).
- S-1020: build the `dadosOpPort` group (`aliqRat`, `fap`), accepted by the JSON schema but never emitted.
- S-1200 (S-1.3): read `observacao` from `infoComplem/sucessaoVinc` instead of `infoComplem`.
- S-1210: `infoReembMed/cnpjOper` is optional in the JSON schema (the XSD requires it only when `indOrgReemb` = 1).
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
