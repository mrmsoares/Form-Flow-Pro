# Traduzir próximo lote de strings para português

Execute o processo de tradução em etapas:

1. **Extrair** o próximo lote de strings não traduzidas (100 strings)
2. **Traduzir** cada string para português brasileiro
3. **Aplicar** as traduções ao arquivo .po
4. **Compilar** o arquivo .mo

## Instruções para o Claude:

1. Execute `php scripts/translation-manager.php extract 100` para gerar o batch
2. Leia o arquivo `languages/translation-batch.txt`
3. Para cada string MSGID, forneça a tradução em MSGSTR
4. Atualize o arquivo com as traduções
5. Execute `php scripts/translation-manager.php apply` para aplicar
6. Execute `php scripts/translation-manager.php compile` para compilar
7. Mostre o status final com `php scripts/translation-manager.php status`

## Regras de Tradução:

- Manter placeholders (%s, %d, %1$s, etc.) na mesma posição relativa
- Manter formatação HTML se existir
- Usar português brasileiro formal
- Manter consistência com termos já traduzidos
- "Form" = "Formulário"
- "Submission" = "Envio"
- "Field" = "Campo"
- "Settings" = "Configurações"
- "Dashboard" = "Painel"
- "Enable/Disable" = "Ativar/Desativar"
