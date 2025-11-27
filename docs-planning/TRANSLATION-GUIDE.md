# Guia de Tradução - FormFlow Pro Enterprise

## 📊 Status Atual

| Métrica | Valor |
|---------|-------|
| Total de strings | 1.968 |
| Traduzidas | 568 (28,9%) |
| Não traduzidas | 1.400 (71,1%) |

---

## 🎯 Estratégia de Tradução em Etapas

Para evitar consumo excessivo de tokens, a tradução é feita em **batches de 100 strings** por sessão.

### Estimativa de Conclusão

- **Strings restantes:** ~1.400
- **Tamanho do batch:** 100 strings
- **Etapas necessárias:** ~14 sessões
- **Tempo por sessão:** ~10-15 minutos

---

## 🛠️ Ferramentas Disponíveis

### 1. Translation Manager (Script PHP)

```bash
# Ver status atual
php scripts/translation-manager.php status

# Extrair próximo batch (100 strings)
php scripts/translation-manager.php extract 100

# Aplicar traduções
php scripts/translation-manager.php apply

# Compilar .mo
php scripts/translation-manager.php compile
```

### 2. Comando Slash do Claude

```
/translate
```

Executa automaticamente todo o processo de tradução de um batch.

---

## 📋 Processo Manual (Passo a Passo)

### Etapa 1: Extrair Strings

```bash
php scripts/translation-manager.php extract 100
```

Isso cria o arquivo `languages/translation-batch.txt` com as próximas 100 strings não traduzidas.

### Etapa 2: Traduzir

O arquivo tem o formato:

```
# [1/100]
MSGID=Original English text
MSGSTR=

# [2/100]
MSGID=Another English text
MSGSTR=
```

Preencha cada `MSGSTR=` com a tradução:

```
# [1/100]
MSGID=Original English text
MSGSTR=Texto original em português

# [2/100]
MSGID=Another English text
MSGSTR=Outro texto em inglês
```

### Etapa 3: Aplicar

```bash
php scripts/translation-manager.php apply
```

### Etapa 4: Compilar

```bash
php scripts/translation-manager.php compile
```

### Etapa 5: Verificar

```bash
php scripts/translation-manager.php status
```

---

## 📝 Glossário de Termos

Para manter consistência nas traduções:

| Inglês | Português |
|--------|-----------|
| Form | Formulário |
| Submission | Envio |
| Field | Campo |
| Settings | Configurações |
| Dashboard | Painel |
| Enable | Ativar |
| Disable | Desativar |
| Save | Salvar |
| Cancel | Cancelar |
| Delete | Excluir |
| Edit | Editar |
| View | Visualizar |
| Export | Exportar |
| Import | Importar |
| Search | Buscar/Pesquisar |
| Filter | Filtrar |
| Sort | Ordenar |
| Status | Status |
| Pending | Pendente |
| Completed | Concluído |
| Failed | Falhou |
| Success | Sucesso |
| Error | Erro |
| Warning | Aviso |
| Info | Informação |
| Webhook | Webhook |
| API Key | Chave de API |
| Token | Token |
| Cache | Cache |
| Queue | Fila |
| Job | Tarefa |
| Log | Log/Registro |
| Report | Relatório |
| Chart | Gráfico |
| Analytics | Analytics |
| Automation | Automação |
| Workflow | Fluxo de trabalho |
| Trigger | Gatilho |
| Action | Ação |
| Condition | Condição |
| Node | Nó |
| Provider | Provedor |
| Integration | Integração |
| Signature | Assinatura |
| Document | Documento |
| Template | Modelo |
| Preview | Pré-visualização |
| Publish | Publicar |
| Draft | Rascunho |

---

## ⚠️ Regras Importantes

### 1. Preservar Placeholders

Os placeholders devem permanecer **exatamente** como no original:

```
✅ Correto:
MSGID=%d items selected
MSGSTR=%d itens selecionados

❌ Incorreto:
MSGID=%d items selected
MSGSTR=itens selecionados %d
```

### 2. Placeholders Posicionais

Quando há múltiplos placeholders, mantenha a ordem ou use posicionais:

```
MSGID=%1$s requires %2$s version %3$s or greater.
MSGSTR=%1$s requer %2$s versão %3$s ou superior.
```

### 3. HTML e Formatação

Preservar tags HTML:

```
MSGID=Click <strong>here</strong> to continue
MSGSTR=Clique <strong>aqui</strong> para continuar
```

### 4. Plurais

O português usa `nplurals=2; plural=(n > 1);`:

```
# Singular
msgid "%d item"
msgstr "%d item"

# Plural
msgid "%d items"
msgstr "%d itens"
```

---

## 🔄 Progresso por Sessão

Após cada sessão, o progresso é salvo em:
`languages/.translation-progress.json`

Exemplo:
```json
{
    "last_batch": 100,
    "total_translated": 568,
    "batches_completed": 5,
    "started_at": "2025-11-27 10:00:00",
    "updated_at": "2025-11-27 15:30:00"
}
```

---

## 🎯 Meta de Conclusão

| Sessão | Strings | Progresso |
|--------|---------|-----------|
| Atual | 568 | 28,9% |
| +1 | 668 | 33,9% |
| +2 | 768 | 39,0% |
| +3 | 868 | 44,1% |
| +4 | 968 | 49,2% |
| +5 | 1068 | 54,3% |
| +6 | 1168 | 59,3% |
| +7 | 1268 | 64,4% |
| +8 | 1368 | 69,5% |
| +9 | 1468 | 74,6% |
| +10 | 1568 | 79,7% |
| +11 | 1668 | 84,8% |
| +12 | 1768 | 89,8% |
| +13 | 1868 | 94,9% |
| +14 | 1968 | 100% ✅ |

---

**Última atualização:** 2025-11-27
