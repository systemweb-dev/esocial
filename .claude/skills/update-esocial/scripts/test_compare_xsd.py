"""Testes do compare_xsd.py com XSD mínimos gravados numa pasta temporária."""
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import compare_xsd  # noqa: E402

TIPOS = '''<?xml version="1.0" encoding="UTF-8"?>
<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">
  <xs:simpleType name="TS_indOrgReemb">
    <xs:restriction base="xs:byte">{enums}</xs:restriction>
  </xs:simpleType>
  <xs:complexType name="T_a"><xs:sequence><xs:element name="tpAmb" type="{tpa}"/></xs:sequence></xs:complexType>
  <xs:complexType name="T_b"><xs:sequence><xs:element name="tpAmb" type="xs:byte"/></xs:sequence></xs:complexType>
</xs:schema>
'''


def write_tipos(folder: Path, enums: list, tpa: str = 'xs:byte') -> Path:
    path = folder / 'tipos.xsd'
    values = ''.join(f'<xs:enumeration value="{v}"/>' for v in enums)
    path.write_text(TIPOS.format(enums=values, tpa=tpa), encoding='utf-8')
    return path


def write_schema(folder: Path, body: str) -> Path:
    path = folder / 'tipos.xsd'
    path.write_text('<xs:schema xmlns:xs="http://www.w3.org/2001/XMLSchema">' + body + '</xs:schema>',
                    encoding='utf-8')
    return path

class CompareTest(unittest.TestCase):
    def setUp(self):
        tmp = tempfile.TemporaryDirectory()
        self.addCleanup(tmp.cleanup)
        self.old = Path(tmp.name, 'old')
        self.new = Path(tmp.name, 'new')
        self.old.mkdir()
        self.new.mkdir()

    def test_valor_novo_em_tipo_nomeado_aparece(self):
        old, new = write_tipos(self.old, ['1', '2']), write_tipos(self.new, ['1', '2', '3'])

        lines = compare_xsd.compare(old, new)

        self.assertIn("  * TS_indOrgReemb: valores +['3'] -[]", lines)

    def test_mudanca_num_tipo_complexo_nao_some_por_outro_tipo_com_o_mesmo_elemento(self):
        old, new = write_tipos(self.old, ['1']), write_tipos(self.new, ['1'], tpa='xs:string')

        lines = compare_xsd.compare(old, new)

        self.assertIn('  * #T_a/tpAmb: tipo xs:byte -> xs:string', lines)

    def test_tipos_iguais_nao_tem_diferenca(self):
        old, new = write_tipos(self.old, ['1', '2']), write_tipos(self.new, ['1', '2'])

        lines = compare_xsd.compare(old, new)

        self.assertEqual(lines, [])

    def test_faceta_alterada_em_tipo_nomeado_aparece(self):
        old = write_schema(self.old, '<xs:simpleType name="TS_nome"><xs:restriction base="xs:string">'
                                     '<xs:maxLength value="60"/></xs:restriction></xs:simpleType>')
        new = write_schema(self.new, '<xs:simpleType name="TS_nome"><xs:restriction base="xs:string">'
                                     '<xs:maxLength value="70"/></xs:restriction></xs:simpleType>')

        lines = compare_xsd.compare(old, new)

        self.assertEqual(lines, ['  * TS_nome: maxLength 60 -> 70'])

    def test_tipo_nomeado_novo_aparece(self):
        old = write_schema(self.old, '')
        new = write_schema(self.new, '<xs:simpleType name="TS_novo"><xs:restriction base="xs:byte"/></xs:simpleType>')

        lines = compare_xsd.compare(old, new)

        self.assertEqual(lines, ['  + novo tipo TS_novo'])


if __name__ == '__main__':
    unittest.main()
