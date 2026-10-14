"""Testes do parser das Mensagens do Sistema com um texto fixo no formato do pdftotext."""
import sys
import unittest
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
import extract_mensagens  # noqa: E402

TEXT = (
    'Mensagens do Sistema\n'
    'Histórico de versões\n'
    '\n'
    'MS0001 - Mensagem simples.\n'
    'MS0002 - O campo não pode ser\n'
    'preenchido: {0}.\n'
    'MS0003 - Com plural.\n'
    'Ações Sugeridas:\n'
    '- Primeira ação\n'
    '- Segunda ação que\n'
    'continua na linha seguinte.\n'
    '\n'
    '7\n'
    '\n'
    '\f'
    'MS0004 - Ação na mesma linha.\n'
    'Ação Sugerida: Fazer tal coisa.\n'
    'MS0005 - Marcador sozinho.\n'
    'Ação sugerida:\n'
    'Parágrafo da ação\n'
    'em duas linhas.\n'
    'MS0006 - Texto com Ação sugerida para evitar o erro: não é marcador.\n'
    'MS0007_V01_00_00 - Variante com sufixo. Ação Sugerida: Ação inline.\n'
    'MS0008 - Marcador repetido.\n'
    'Ação Sugerida: Ação Sugerida: Sem repetição.\n'
    'MS0009 - Ação que atravessa a página.\n'
    'Ação Sugerida:\n'
    'Parágrafo que termina na\n'
    '\n'
    '13\n'
    '\f'
    'página seguinte.\n'
    '\n'
    '12\n'
    '9\n'
)


class ParseTest(unittest.TestCase):
    def setUp(self):
        self.messages = extract_mensagens.parse(TEXT)

    def test_ignora_o_que_vem_antes_do_ms0001(self):
        codes = list(self.messages)

        self.assertEqual(codes[0], 'MS0001')

    def test_mensagem_simples(self):
        message = self.messages['MS0001']

        self.assertEqual(message, {'texto': 'Mensagem simples.', 'acoes': []})

    def test_une_as_linhas_do_texto_e_mantem_o_marcador_de_parametro(self):
        message = self.messages['MS0002']

        self.assertEqual(message['texto'], 'O campo não pode ser preenchido: {0}.')

    def test_acoes_em_itens_com_continuacao_e_sem_numero_de_pagina(self):
        message = self.messages['MS0003']

        self.assertEqual(message['acoes'], ['Primeira ação', 'Segunda ação que continua na linha seguinte.'])

    def test_acao_na_mesma_linha_do_marcador(self):
        message = self.messages['MS0004']

        self.assertEqual(message['acoes'], ['Fazer tal coisa.'])

    def test_paragrafo_depois_do_marcador_vira_uma_acao(self):
        message = self.messages['MS0005']

        self.assertEqual(message['acoes'], ['Parágrafo da ação em duas linhas.'])

    def test_acao_sugerida_no_meio_da_frase_fica_no_texto(self):
        message = self.messages['MS0006']

        self.assertEqual(message, {'texto': 'Texto com Ação sugerida para evitar o erro: não é marcador.', 'acoes': []})

    def test_variante_com_sufixo_e_marcador_no_meio_da_linha(self):
        message = self.messages['MS0007_V01_00_00']

        self.assertEqual(message, {'texto': 'Variante com sufixo.', 'acoes': ['Ação inline.']})

    def test_marcador_repetido_e_numero_de_pagina_partido_no_fim(self):
        message = self.messages['MS0008']

        self.assertEqual(message, {'texto': 'Marcador repetido.', 'acoes': ['Sem repetição.']})

    def test_paragrafo_de_acao_que_atravessa_a_pagina_continua_uma_acao(self):
        message = self.messages['MS0009']

        self.assertEqual(message['acoes'], ['Parágrafo que termina na página seguinte.'])


if __name__ == '__main__':
    unittest.main()
