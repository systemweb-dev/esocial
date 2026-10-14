# Tools

`NFePHP\eSocial\Tools` assina e envia lotes de eventos e faz as consultas e downloads nos
webservices do eSocial. Antes de enviar, valida a requisição no XSD do pacote de comunicação do
`serviceVersion` (ver [Config.md](Config.md)).

```php
use NFePHP\Common\Certificate;
use NFePHP\eSocial\Tools;

$certificate = Certificate::readPfx($conteudoPfx, $senha);
$tools = new Tools($configJson, $certificate);
```

Todos os métodos devolvem a resposta SOAP do governo como string (também em
`$tools->lastResponse`; o corpo enviado fica em `$tools->lastRequest`). Para ler a resposta, use
`NFePHP\eSocial\Common\Standardize` (exemplo no [README](../README.md#forma-de-uso)).

## Envio

| Método | O que faz |
|---|---|
| `enviarLoteEventos($grupo, $eventos)` | Envia até 50 eventos montados pela lib (`Event::...`, array). Evento criado sem certificado recebe o do `Tools` e é assinado no envio, desde que o `toXML()` dele não tenha sido chamado antes (o XML gerado fica guardado no objeto, sem assinatura). Lista vazia não envia nada e devolve `''`. |
| `enviarLoteXmlEventos($grupo, $xmls)` | Envia até 50 XML de eventos **já assinados** (strings). Lê o Id e o grupo de cada XML. |

`$grupo` é um dos três grupos do lote (Manual de Orientação S-1.3, seção 10):

| Constante | Valor | Eventos |
|---|---|---|
| `Tools::EVT_TABELAS` | 1 | S-1000, S-1005, S-1010, S-1020, S-1070 |
| `Tools::EVT_NAO_PERIODICOS` | 2 | S-2190 a S-2555, S-3000, S-3500 |
| `Tools::EVT_PERIODICOS` | 3 | S-1200 a S-1299 |

`EVT_INICIAIS` é alias obsoleto de `EVT_TABELAS`. A lista exata está em
[docs/eventos](eventos/README.md). Erros:

- `InvalidArgumentException`: grupo diferente de 1, 2 e 3; mais de 50 eventos; `serviceVersion`
  sem pacote de comunicação na lib ("Schemas não localizados").
- `RuntimeException`: evento de outro grupo; XML sem evento do eSocial identificável; eventos de
  grupos diferentes no mesmo lote; grupo declarado diferente do grupo dos XML; certificado vencido
  ("The validity of the certificate has expired.", quando o `SoapCurl` carrega o certificado).

## Consultas

| Método | Serviço |
|---|---|
| `consultarLoteEventos($protocolo)` | Resultado do processamento de um lote, pelo `protocoloEnvio` do envio. |
| `consultarEventosEmpregador($tpEvt, $perApur)` | Ids dos eventos do empregador (`S-1200`, `'2026-09'`). |
| `consultarEventosTabela($tpEvt, $chEvt = null, $dtIni = null, $dtFim = null)` | Ids dos eventos de tabela (`S-1010`, chave e período opcionais). |
| `consultarEventosTrabalhador($cpfTrab, $dtIni, $dtFim)` | Ids dos eventos de um trabalhador entre duas datas (`'2026-01-01T00:00:00'`). |
| `downloadEventosPorId($ids)` | XML dos eventos pelos Ids (array). |
| `downloadEventosPorNrRecibo($nrRecs)` | XML dos eventos pelos números de recibo (array). |

As consultas de identificadores e os downloads vão assinados; a consulta de lote não.
`tpAmb` do config escolhe o endereço: Produção Restrita (`2`) ou Produção (`1`).

## Conexão (TLS) e certificado

Por padrão o `Tools` cria um `SoapCurl` com o certificado em memória e **verifica** o certificado
do servidor no store de CAs do sistema (os hosts do eSocial usam a raiz pública GlobalSign Root
R46; o pacote `ca-certificates` precisa estar instalado). Para mudar a conexão, monte o
`SoapCurl` e entregue ao `Tools`:

```php
use NFePHP\eSocial\Common\Soap\SoapCurl;

$soap = new SoapCurl($certificate);
$soap->timeout(60);
$soap->loadCA('/caminho/bundle.pem'); // SUBSTITUI o store do sistema; lança se o arquivo não existir
$tools->loadSoapClass($soap);
```

`$soap->disableSecurity(true)` desliga a verificação do servidor: só para ambiente controlado.
Nunca rode com Xdebug ou `display_errors` ligados onde há certificado: o stack trace pode
imprimir a chave.
