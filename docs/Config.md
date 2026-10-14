# Config

String JSON passada a todo evento (`Event::...`) e ao `Tools`. Identifica o ambiente, o
leiaute, o pacote de comunicação, o empregador e o transmissor.

```php
$configJson = json_encode([
    'tpAmb' => 2,                     // 1 = Produção; 2 = Produção Restrita
    'verProc' => 'MeuSistema_1.0',    // versão do aplicativo que gera o evento
    'eventoVersion' => 'S.1.3.0',     // leiaute dos eventos (só S-1.3)
    'serviceVersion' => '1.6.0',      // pacote de comunicação (1.6-Alfa: aceita CNPJ alfanumérico)
    'empregador' => [
        'tpInsc' => 1,                // 1 = CNPJ; 2 = CPF
        'nrInsc' => '12345678',       // CNPJ: raiz de 8 posições (14 para órgão público federal)
        'nmRazao' => 'Empresa Exemplo Ltda',
    ],
    'transmissor' => [
        'tpInsc' => 1,                // 1 = CNPJ; 2 = CPF
        'nrInsc' => '12345678000195', // número completo de quem assina e envia
    ],
]);
```

| Chave | Obrigatória | Uso |
|---|---|---|
| `tpAmb` | sim | Ambiente: `1` Produção, `2` Produção Restrita. Define o `tpAmb` do evento e o endereço do webservice. |
| `verProc` | sim | Versão do processo de emissão (`verProc` do evento), até 20 caracteres. |
| `eventoVersion` | sim | Leiaute dos eventos. Só `S.1.3.0`: abaixo de 1.3 o construtor do evento lança `InvalidArgumentException` (o S-1.2 foi encerrado em 02/02/2025). |
| `serviceVersion` | sim no `Tools` | Pacote de comunicação usado para envio e consulta de lotes (pasta `schemes/comunicacao/v1_6_0`). Com `1.5.0` o lote de CNPJ alfanumérico é recusado localmente. |
| `empregador.tpInsc` | sim | Tipo de inscrição do empregador (`ideEmpregador`). |
| `empregador.nrInsc` | sim | Inscrição do empregador. Entra também no Id do evento. |
| `empregador.nmRazao` | sim no `Tools` | Razão social. O `Tools` lê no construtor; não vai no XML do evento. |
| `transmissor.tpInsc` | sim no `Tools` | Tipo de inscrição do transmissor (`ideTransmissor` do lote). |
| `transmissor.nrInsc` | sim no `Tools` | Inscrição completa do transmissor. |

## Id do evento e `sequencial`

O Id (`$evento->evtid`) é `ID` + `tpInsc` + `nrInsc` (completado com zeros à direita até 14
posições) + data e hora (`AAAAMMDDhhmmss`) + sequencial de 5 dígitos. Sem `$std->sequencial` a lib
sorteia um de 1 a 99999; com ele (inteiro de 1 a 99999) usa o informado. Num mesmo processo a lib
troca o sequencial se o Id já foi usado. Entre processos (vários workers do mesmo empregador) quem
chama deve alocar o `sequencial` para não repetir Id no mesmo segundo.

## Certificado

O certificado A1 é lido em memória com `NFePHP\Common\Certificate::readPfx($conteudoPfx, $senha)`.
A lib não grava a chave em disco: a conexão usa `CURLOPT_SSLCERT_BLOB`/`CURLOPT_SSLKEY_BLOB`.
