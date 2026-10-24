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


class MensagensTest(unittest.TestCase):
    def test_versao_aplicada_igual_a_publicada_nao_e_novidade(self):
        published = ['2.5']

        news = check.msgs_is_news(published, '2.5')

        self.assertFalse(news)

    def test_nunca_revisada_e_novidade(self):
        published = ['2.5']

        news = check.msgs_is_news(published, None)

        self.assertTrue(news)

    def test_versao_maior_publicada_e_novidade(self):
        published = ['2.5', '2.6']

        news = check.msgs_is_news(published, '2.5')

        self.assertTrue(news)


class PendentesTest(unittest.TestCase):
    STATE = {'pending_packages': [
        {'xsd_package': '2026-11-23', 'branch': 'feature/xsd-2026-11-23', 'merge_on': '2026-11-09'},
    ]}

    def test_pacote_pronto_em_branch(self):
        ready = check.pending_branch(self.STATE, '2026-11-23')

        self.assertEqual(ready, {'xsd_package': '2026-11-23', 'branch': 'feature/xsd-2026-11-23', 'merge_on': '2026-11-09'})

    def test_pacote_sem_branch(self):
        ready = check.pending_branch(self.STATE, '2026-12-14')

        self.assertIsNone(ready)

    def test_estado_sem_a_lista(self):
        ready = check.pending_branch({}, '2026-11-23')

        self.assertIsNone(ready)


if __name__ == '__main__':
    unittest.main()


class NotaTecnicaTest(unittest.TestCase):
    def test_revisao_mais_recente_e_a_ultima(self):
        page = (
            '<a href="nt-07-rev-20-10.pdf">Nota Técnica v. S-1.3 07/2026 rev. 20/10/2026</a>'
            '<a href="nt-07-rev.pdf">Nota Técnica v. S-1.3 07/2026 rev. 24/09/2026</a>'
            '<a href="nt-07.pdf">Nota Técnica v. S-1.3 07/2026</a>'
            '<a href="nt-06-rev.pdf">Nota Técnica v. S-1.3 06/2026 rev. 09/04/2026</a>'
        )

        found = check.parse(page)

        self.assertEqual(found['nts'][-1], 'Nota Técnica v. S-1.3 07/2026 rev. 20/10/2026')
