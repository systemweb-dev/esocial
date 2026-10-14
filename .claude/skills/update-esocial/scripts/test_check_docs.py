"""Testes do check_docs.py com um repositório mínimo gravado numa pasta temporária."""
import sys
import tempfile
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import check_docs  # noqa: E402

SOURCES = {
    'src/Tools.php': '<?php class Tools { public function enviarLoteEventos($g, $e) {} }',
    'src/Common/Tools.php': '<?php class Tools { const EVT_TABELAS = 1; public function __construct() {} }',
    'src/Event.php': "<?php 'evtinfoempregador' => Factories\\EvtInfoEmpregador::class, 's1000' => 'evtinfoempregador',",
    'src/Common/SystemMessages.php': '<?php class SystemMessages { public static function get($c) {} }',
    'src/Common/Soap/SoapCurl.php': '<?php class SoapCurl { public function send() {} }',
    'src/Common/Soap/SoapBase.php': '<?php class SoapBase { public function loadCA($p) {} }',
    'src/Common/Standardize.php': '<?php class Standardize { public function toStd($x = null) {} }',
    'docs/eventos/README.md': '# Eventos\n',
}


class ProblemsTest(unittest.TestCase):
    def setUp(self):
        tmp = tempfile.TemporaryDirectory()
        self.addCleanup(tmp.cleanup)
        self.root = Path(tmp.name)
        for name, text in SOURCES.items():
            (self.root / name).parent.mkdir(parents=True, exist_ok=True)
            (self.root / name).write_text(text, encoding='utf-8')

    def write_readme(self, text: str) -> None:
        (self.root / 'README.md').write_text(text, encoding='utf-8')

    def test_doc_que_so_cita_api_existente(self):
        self.write_readme(
            "$tools->enviarLoteEventos(Tools::EVT_TABELAS, [$evento]);\n"
            "Event::evtInfoEmpregador($c, $std); Event::s1000($c, $std);\n"
            "SystemMessages::get(191); $soap->loadCA('/ca.pem'); $standardize->toStd($xml);\n"
            "[eventos](docs/eventos/README.md) [gov.br](https://www.gov.br/esocial) [topo](#uso)\n")

        found = check_docs.problems(self.root, ['README.md'])

        self.assertEqual(found, [])

    def test_acusa_metodo_constante_evento_e_link_inexistentes(self):
        self.write_readme(
            "$tools->enviarLote(1, []); Tools::EVT_INICIO; Event::s9999($c, $std);\n"
            "SystemMessages::all(); $soap->naoExiste(); [x](docs/Nada.md#a)\n")

        found = check_docs.problems(self.root, ['README.md'])

        self.assertEqual(found, [
            'README.md: Tools enviarLote não existe',
            'README.md: SoapCurl naoExiste não existe',
            'README.md: Tools:: EVT_INICIO não existe',
            'README.md: Event s9999 não existe',
            'README.md: SystemMessages all não existe',
            'README.md: link quebrado docs/Nada.md#a',
        ])

    def test_link_com_titulo_ou_entre_sinais_e_constante_com_digito_sao_conferidos(self):
        self.write_readme('[a](docs/Falta.md "titulo") [b](<docs/Outro.md>) Tools::EVT_V2\n')

        found = check_docs.problems(self.root, ['README.md'])

        self.assertEqual(found, [
            'README.md: Tools:: EVT_V2 não existe',
            'README.md: link quebrado docs/Falta.md',
            'README.md: link quebrado docs/Outro.md',
        ])

    def test_construtor_nao_conta_como_metodo_chamavel(self):
        self.write_readme('$tools->__construct();\n')

        found = check_docs.problems(self.root, ['README.md'])

        self.assertEqual(found, ['README.md: Tools __construct não existe'])


if __name__ == '__main__':
    unittest.main()
