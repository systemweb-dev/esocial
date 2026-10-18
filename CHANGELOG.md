# Changelog

All Notable changes to sped-esocial will be documented in this file.

Updates should follow the [Keep a CHANGELOG](http://keepachangelog.com/) principles.

## Unreleased

## 2.0.4 - 2026-10-18

### Fixed
- JSON schemas follow the occurrence of the XSD and the layout, relative to the parent group. Optional in the XSD, no longer refused by the lib (the conditional rule is left to the government): S-1200/S-1202 `ideadv.vlradv`; S-1210 `infodep.nome` and `infoprocret.codsusp`; S-2200 `endereco`, `trabimig.tmpresid`, `dependente[].tpdep` and `inctrab`; S-2205 `endereco`, `exterior.codpostal`, `dependente[].tpdep` and `inctrab`; S-2206 `localtempdom.tplograd` and `horcontratual.qtdhrssem`; S-2210 `ultdiatrab`, `houveafast` and `atestado.ufoc`; S-2299 `ideadc.dtacconv`; S-2300 `endereco`, `exterior.codpostal`, `dependente[].tpdep` and `cargofuncao.cbocargo`/`cbofuncao`; S-2400/S-2405 `dependente[].tpdep`; S-2501 `infoprocret.codsusp`. The builders of these fields (and of `endereco`) no longer write an empty tag when they are absent. Required in the XSD, now refused before building: S-1200 `procjudtrab.codsusp` and `itensremun.vrrubr`; S-2200/S-2206 `alvarajudicial.nrprocjud`; S-2200 `mudancacpf.matricant`; S-2210 `codsitgeradora`, `atestado` and `atestado.dsclesao`; S-2220 `exame.procrealizado`, `medico.nmmed` and `respmonit.nmresp`/`nrcrm`/`ufcrm`; S-2231 `matricula`; S-2240 `epi[].docaval`; S-2300 `fgts.dtopcfgts` and `infodirigentesindical.tpregprev`; S-2399 `procjudtrab.codsusp`.
- JSON schemas no longer declare fields the S-1.3 layout does not have or the builder never reads: S-1200 `dmdev.infoperapur` and `dmdev.infoperant` (the builder reads `dmdev.ideestablot` and `dmdev.ideadc`), `detoper`, `infotrabinterm`, `compacconv` and `dtefacconv`; S-2220 `medico.cpfmed`/`nismed`; S-2240 `epi[].dscepi`. S-2306 `estagiario.supervisorestagio` (was `supervisor`, so the group was silently dropped).
- S-2190: `dtTerm` was read from the camelCase key and never reached the XML.
- S-2399: `indGuia` is written in `ideEvento`.
- S-2500: `duracao.dtTerm` was read from the misspelled key `dtrerm`; `mudCategAtiv` and `unicContr` were only written when `infoCompl` was informed.

## 2.0.3 - 2026-10-16

### Fixed
- JSON schemas now declare the fields the S-1.3 builders read, with the XSD names: S-2200 `infodeficiencia` (was `deficiencia`), `dependente[].depirrf` (the key was a duplicated `depsf`) and `infoceletista.matanotjud`; S-2299 `verbasresc.proccs` (was `procs`); S-2240 `agnoc[].unmed` (was `umed`); S-2300 `cargofuncao` (was `cargoduncao`); S-2306 `mandelet` (was `mandelelt`); S-2500 `duracao.clauassec` (was `clouassec`); S-2220 `exmedocup.respmonit` (was outside `properties`, so never checked); S-1260 `indguia` and `nfs[].vrratdescpr`; S-2400 `nrrecibo`; S-3500 `ideproctrab.ideseqproc`; S-1200 `indapurir` in `remunperant.itensremun`. With the old names the group was silently dropped from the XML.
- S-1207: the items of `infoperant…itensremun` had no `type`/`properties` wrapper, so none of their rules were applied.
- S-1260: `indguia` read with `?? null` (no undefined-property warning when absent).
- S-2500 example: `infoccp` and `dscsalvar` (were `infocccp` and `descsalvar`).
- New test: every field used by an example must be declared in the event's JSON schema, and the fixed groups must reach the XML.

## 2.0.2 - 2026-10-16

### Fixed
- Validation messages in pt-BR: "A propriedade X é obrigatória" (was "é obrigatório"); type errors read "X encontrado, mas o esperado é uma string" for any type; "Invalid date-time" is no longer cut by the "Invalid date" rule; spelling and accents (para, repetições, número, booleano, mínimo, múltiplo, divisível, disponível).

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
