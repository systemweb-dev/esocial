"""Testes do gen_docs.py com um repositório mínimo gravado numa pasta temporária."""
import json
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import gen_docs  # noqa: E402

EVENT_PHP = '''<?php
    public static $available = [
        'evtteste' => Factories\\EvtTeste::class,
    ];
    public static $aliases = [
        's1999' => 'evtteste',
    ];
'''
TOOLS_PHP = '''<?php
    protected $grupos = [
        1 => [
            'S-1999',
        ],
        2 => [
            'S-2999',
        ],
    ];
'''
XSD = '''<?xml version="1.0" encoding="UTF-8"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
  <xs:element name="eSocial"><xs:complexType><xs:sequence>
    <xs:element name="evtTeste">
      <xs:annotation>
        <xs:documentation>Evento Teste</xs:documentation>
        <xs:documentation>DESCRICAO_COMPLETA:Evento Teste da Lib.</xs:documentation>
        <xs:documentation>CHAVE_GRUPO: {Id}</xs:documentation>
      </xs:annotation>
      <xs:complexType><xs:sequence>
        <xs:element name="ideEvento" type="T_ideEvento_mensal"/>
        <xs:element name="ideEmpregador" type="T_ideEmpregador"/>
        <xs:element name="infoValor">
          <xs:complexType><xs:sequence>
            <xs:element name="tpInsc">
              <xs:annotation>
                <xs:documentation>Tipo de inscrição do estabelecimento.</xs:documentation>
                <xs:documentation>CONDICAO_GRUPO: O</xs:documentation>
                <xs:documentation>Validação: Deve ser 1 ou 4.</xs:documentation>
              </xs:annotation>
            </xs:element>
            <xs:element name="dtIni" type="TS_dtIni"/>
            <xs:element name="detItem" type="T_detItem"/>
            <xs:element name="infoOutro"><xs:complexType><xs:sequence>
              <xs:element name="tpReg"><xs:annotation><xs:documentation>Regime do outro.</xs:documentation></xs:annotation></xs:element>
            </xs:sequence></xs:complexType></xs:element>
            <xs:element name="infoCedido"><xs:complexType><xs:sequence>
              <xs:element name="tpReg"><xs:annotation><xs:documentation>Regime do cedido.</xs:documentation></xs:annotation></xs:element>
            </xs:sequence></xs:complexType></xs:element>
            <xs:element name="vlr"><xs:simpleType>
              <xs:annotation><xs:documentation>Valor do item.</xs:documentation></xs:annotation>
              <xs:restriction base="xs:decimal"/>
            </xs:simpleType></xs:element>
          </xs:sequence>
          <xs:attribute name="perRef"><xs:annotation><xs:documentation>Período de referência.</xs:documentation></xs:annotation></xs:attribute>
          </xs:complexType>
        </xs:element>
      </xs:sequence></xs:complexType>
    </xs:element>
  </xs:sequence></xs:complexType></xs:element>
</xs:schema>
'''
TIPOS = '''<?xml version="1.0" encoding="UTF-8"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
  <xs:complexType name="T_ideEmpregador"><xs:sequence>
    <xs:element name="tpInsc"><xs:annotation><xs:documentation>Tipo de inscrição do empregador.</xs:documentation></xs:annotation></xs:element>
  </xs:sequence></xs:complexType>
  <xs:complexType name="T_ideEvento_folha"><xs:sequence>
    <xs:element name="perApur"><xs:annotation><xs:documentation>Período da folha.</xs:documentation></xs:annotation></xs:element>
  </xs:sequence></xs:complexType>
  <xs:complexType name="T_ideEvento_mensal"><xs:sequence>
    <xs:element name="perApur"><xs:annotation>
      <xs:documentation>Período mensal.</xs:documentation>
      <xs:documentation>Validação: Devem ser observadas as regras:</xs:documentation>
      <xs:documentation>a) ser um mês válido;</xs:documentation>
      <xs:documentation>REGRA:REGRA_X</xs:documentation>
    </xs:annotation></xs:element>
  </xs:sequence></xs:complexType>
  <xs:complexType name="T_detItem"><xs:sequence>
    <xs:element name="tpInsc"><xs:annotation><xs:documentation>Tipo de inscrição do prestador.</xs:documentation></xs:annotation></xs:element>
  </xs:sequence></xs:complexType>
  <xs:simpleType name="TS_dtIni">
    <xs:annotation><xs:documentation>Data de início.</xs:documentation></xs:annotation>
    <xs:restriction base="xs:date"/>
  </xs:simpleType>
</xs:schema>
'''
SCHEMA = {
    'title': 'evtTeste',
    'type': 'object',
    'properties': {
        'sequencial': {'required': False, 'type': ['integer', 'null'], 'minimum': 1, 'maximum': 99999},
        'perapur': {'required': True, 'type': 'string'},
        'infovalor': {
            'required': True,
            'type': 'array',
            'minItems': 1,
            'maxItems': 99,
            'items': {'type': 'object', 'properties': {
                'tpinsc': {'required': True, 'type': 'integer', 'minimum': 1, 'maximum': 4},
                'dtini': {'required': False, 'type': ['string', 'null'], '$ref': '#/definitions/data'},
                'vlr': {'required': True, 'type': 'number'},
                'cedido': {'required': False, 'type': ['object', 'null'], 'properties': {
                    'tpreg': {'required': True, 'type': 'string'},
                }},
                'grupoxyz': {'required': False, 'type': ['object', 'null'], 'properties': {
                    'tpreg': {'required': True, 'type': 'string'},
                }},
                'detitem': {'required': False, 'type': ['object', 'null'], 'properties': {
                    'tpinsc': {'required': True, 'type': 'string'},
                }},
                'perref': {'required': True, 'type': 'string'},
                'campoantigo': {'required': False, 'type': ['string', 'null']},
            }},
        },
    },
}


def make_repo(root: Path) -> None:
    files = {
        'src/Event.php': EVENT_PHP,
        'src/Common/Tools.php': TOOLS_PHP,
        'schemes/v_S_01_03_00/evtTeste.xsd': XSD,
        'schemes/v_S_01_03_00/tipos.xsd': TIPOS,
        'jsonSchemes/v_S_01_03_00/evtTeste.schema': json.dumps(SCHEMA),
        'jsonSchemes/definitions.schema': json.dumps({'definitions': {'data': {'pattern': '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'}}}),
        'ESOCIAL-VERSION.json': json.dumps({'xsd_package': '2026-10-26'}),
        'examples/Fake/v_S_01_03_00/Fake_s1999_EvtTeste.php': '<?php\n',
    }
    for name, text in files.items():
        (root / name).parent.mkdir(parents=True, exist_ok=True)
        (root / name).write_text(text, encoding='utf-8')


class GenerateTest(unittest.TestCase):
    def setUp(self):
        tmp = tempfile.TemporaryDirectory()
        self.addCleanup(tmp.cleanup)
        self.root = Path(tmp.name)
        make_repo(self.root)

    def row(self, files: dict, field: str) -> str:
        return next(line for line in files['docs/eventos/S-1999.md'].splitlines() if line.startswith(f'| `{field}` |'))

    def test_gera_o_indice_e_uma_pagina_por_evento(self):
        files = gen_docs.generate(self.root)

        self.assertEqual(sorted(files), ['docs/eventos/README.md', 'docs/eventos/S-1999.md'])

    def test_cabecalho_com_nome_classe_grupo_e_exemplo(self):
        files = gen_docs.generate(self.root)

        text = files['docs/eventos/S-1999.md']
        self.assertTrue(text.startswith('# S-1999 — Teste da Lib\n'))
        self.assertIn('| Classe | `NFePHP\\eSocial\\Factories\\EvtTeste` |', text)
        self.assertIn('| Montar | `Event::s1999($configJson, $std, $certificate)` |', text)
        self.assertIn('| Grupo do lote | 1 (`Tools::EVT_TABELAS`) |', text)
        self.assertIn('| Exemplo | [Fake_s1999_EvtTeste.php](../../examples/Fake/v_S_01_03_00/Fake_s1999_EvtTeste.php) |', text)
        self.assertIn('| Pacote XSD | 2026-10-26 |', text)

    def test_campo_em_array_com_desempate_pelo_grupo_e_linha_de_validacao(self):
        files = gen_docs.generate(self.root)

        self.assertEqual(self.row(files, 'infovalor/tpinsc'),
                         '| `infovalor/tpinsc` | integer | sim | 1 a 4 | '
                         'Tipo de inscrição do estabelecimento. Validação: Deve ser 1 ou 4. |')

    def test_ref_resolvido_e_descricao_do_tipo_nomeado(self):
        files = gen_docs.generate(self.root)

        self.assertEqual(self.row(files, 'infovalor/dtini'),
                         '| `infovalor/dtini` | string | não | `^[0-9]{4}-[0-9]{2}-[0-9]{2}$` | Data de início. |')

    def test_descricao_do_tipo_anonimo_e_do_atributo(self):
        files = gen_docs.generate(self.root)

        self.assertTrue(self.row(files, 'infovalor/vlr').endswith('| Valor do item. |'))
        self.assertTrue(self.row(files, 'infovalor/perref').endswith('| Período de referência. |'))

    def test_grupo_achatado_no_json_usa_o_tipo_que_o_evento_referencia(self):
        files = gen_docs.generate(self.root)

        self.assertEqual(self.row(files, 'perapur'),
                         '| `perapur` | string | sim |  | '
                         'Período mensal. Validação: Devem ser observadas as regras: a) ser um mês válido; |')

    def test_elemento_de_tipo_nomeado_usa_o_filho_do_tipo(self):
        files = gen_docs.generate(self.root)

        self.assertTrue(self.row(files, 'infovalor/detitem/tpinsc').endswith('| Tipo de inscrição do prestador. |'))

    def test_grupo_com_prefixo_info_no_xsd(self):
        files = gen_docs.generate(self.root)

        self.assertTrue(self.row(files, 'infovalor/cedido/tpreg').endswith('| Regime do cedido. |'))

    def test_campo_da_lib_tem_descricao_propria(self):
        files = gen_docs.generate(self.root)

        self.assertIn('Sequencial do Id do evento', self.row(files, 'sequencial'))

    def test_campo_sem_par_no_xsd_vai_para_a_secao_propria(self):
        files = gen_docs.generate(self.root)

        text = files['docs/eventos/S-1999.md']
        self.assertTrue(text.endswith('## Campos sem correspondência no XSD\n\n'
                                      'Aceitos pelo JSON schema da lib, sem elemento de mesmo nome no XSD ou com grupo de nome '
                                      'diferente e mais de um elemento possível:\n\n'
                                      '- `infovalor/cedido`\n'
                                      '- `infovalor/grupoxyz`\n'
                                      '- `infovalor/grupoxyz/tpreg`\n'
                                      '- `infovalor/campoantigo`\n'))

    def test_indice_lista_o_evento(self):
        files = gen_docs.generate(self.root)

        self.assertIn('| [S-1999](S-1999.md) | `evtTeste` | `EvtTeste` | 1 |', files['docs/eventos/README.md'])


class OutdatedTest(unittest.TestCase):
    def setUp(self):
        tmp = tempfile.TemporaryDirectory()
        self.addCleanup(tmp.cleanup)
        self.root = Path(tmp.name)
        make_repo(self.root)
        self.files = gen_docs.generate(self.root)
        for name, text in self.files.items():
            (self.root / name).parent.mkdir(parents=True, exist_ok=True)
            (self.root / name).write_text(text, encoding='utf-8')

    def test_em_dia(self):
        stale = gen_docs.outdated(self.root, self.files)

        self.assertEqual(stale, [])

    def test_pagina_alterada_e_pagina_que_sobrou(self):
        (self.root / 'docs/eventos/S-1999.md').write_text('editado à mão\n', encoding='utf-8')
        (self.root / 'docs/eventos/S-1030.md').write_text('evento extinto\n', encoding='utf-8')

        stale = gen_docs.outdated(self.root, self.files)

        self.assertEqual(stale, ['docs/eventos/S-1030.md', 'docs/eventos/S-1999.md'])


if __name__ == '__main__':
    unittest.main()
