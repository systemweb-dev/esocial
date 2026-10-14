"""Testes do check.py sem rede: a página é um trecho fixo do Portal do eSocial."""
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import check  # noqa: E402

PAGE = (
    '<a href="https://www.gov.br/esocial/pt-br/documentacao-tecnica/manuais/2026-10-26_esquemas_xsd_v_s_01_03_00-1.zip">XSD</a>'
    '<a class="outstanding-link" href="https://www.gov.br/esocial/pt-br/documentacao-tecnica/manuais/pacote-de-comunicacao-esocial-v1-6.zip">Pacote de Comunicação eSocial v.1.6</a>'
    '<a class="outstanding-link" href="https://www.gov.br/esocial/pt-br/documentacao-tecnica/manuais/pacote-de-comunicacao-esocial-v1-6-alfa.zip">Pacote de Comunicação eSocial v.1.6 - Alfa</a>'
)


class ComunicacaoTest(unittest.TestCase):
    def test_lista_a_versao_base_e_a_alfa(self):
        found = check.parse(PAGE)

        self.assertEqual(sorted(found['comm']), ['1.6', '1.6-Alfa'])

    def test_alfa_aplicada_nao_e_novidade_mesmo_com_a_base_publicada(self):
        published = check.parse(PAGE)['comm']

        news = check.comm_is_news(published, '1.6.0-Alfa')

        self.assertFalse(news)

    def test_versao_anterior_aplicada_e_novidade(self):
        published = check.parse(PAGE)['comm']

        news = check.comm_is_news(published, '1.5.0')

        self.assertTrue(news)

    def test_versao_maior_publicada_e_novidade(self):
        published = ['1.6-Alfa', '1.7']

        news = check.comm_is_news(published, '1.6.0-Alfa')

        self.assertTrue(news)


if __name__ == '__main__':
    unittest.main()
