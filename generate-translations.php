<?php
/**
 * Translation String Extractor and Generator
 *
 * Extracts all translatable strings from PHP files and generates
 * complete .pot and .po files with pt_BR translations.
 *
 * @package FormFlowPro
 * @since 2.0.0
 */

// Portuguese (Brazil) translations dictionary
$translations = [
    // Core UI
    'FormFlow Pro' => 'FormFlow Pro',
    'FormFlow Pro Enterprise' => 'FormFlow Pro Enterprise',
    'Dashboard' => 'Painel',
    'Forms' => 'Formulários',
    'Submissions' => 'Envios',
    'Analytics' => 'Análises',
    'Settings' => 'Configurações',
    'Tools' => 'Ferramentas',
    'Save' => 'Salvar',
    'Cancel' => 'Cancelar',
    'Delete' => 'Excluir',
    'Edit' => 'Editar',
    'View' => 'Visualizar',
    'Add' => 'Adicionar',
    'Add New' => 'Adicionar Novo',
    'Search' => 'Buscar',
    'Filter' => 'Filtrar',
    'Export' => 'Exportar',
    'Import' => 'Importar',
    'Close' => 'Fechar',
    'Loading...' => 'Carregando...',
    'Actions' => 'Ações',
    'Status' => 'Status',
    'Name' => 'Nome',
    'Email' => 'E-mail',
    'Date' => 'Data',
    'Created' => 'Criado',
    'Updated' => 'Atualizado',
    'Active' => 'Ativo',
    'Inactive' => 'Inativo',
    'Enabled' => 'Habilitado',
    'Disabled' => 'Desabilitado',
    'Yes' => 'Sim',
    'No' => 'Não',
    'All' => 'Todos',
    'None' => 'Nenhum',
    'Select' => 'Selecionar',
    'Select...' => 'Selecione...',
    'Next' => 'Próximo',
    'Previous' => 'Anterior',
    'First' => 'Primeiro',
    'Last' => 'Último',
    'Reset' => 'Redefinir',
    'Apply' => 'Aplicar',
    'Apply Filters' => 'Aplicar Filtros',
    'Clear' => 'Limpar',
    'Refresh' => 'Atualizar',
    'Download' => 'Baixar',
    'Upload' => 'Enviar',
    'Copy' => 'Copiar',
    'Duplicate' => 'Duplicar',
    'Archive' => 'Arquivar',
    'Restore' => 'Restaurar',
    'Confirm' => 'Confirmar',
    'Success' => 'Sucesso',
    'Error' => 'Erro',
    'Warning' => 'Aviso',
    'Info' => 'Informação',
    'Help' => 'Ajuda',
    'Documentation' => 'Documentação',
    'Support' => 'Suporte',
    'Version' => 'Versão',
    'License' => 'Licença',
    'Licensed' => 'Licenciado',
    'Unlicensed' => 'Não Licenciado',
    'Valid' => 'Válido',
    'Invalid' => 'Inválido',
    'Expired' => 'Expirado',
    'Premium' => 'Premium',
    'Free' => 'Gratuito',
    'Pro' => 'Pro',
    'Enterprise' => 'Enterprise',

    // Autentique
    'Autentique' => 'Autentique',
    'Autentique Documents' => 'Documentos Autentique',
    'Autentique not configured!' => 'Autentique não configurado!',
    'Please configure your Autentique API key in settings to start using digital signatures.' => 'Por favor, configure sua chave API do Autentique nas configurações para começar a usar assinaturas digitais.',
    'Go to Settings' => 'Ir para Configurações',
    'Total Documents' => 'Total de Documentos',
    'Pending Signature' => 'Pendente de Assinatura',
    'Signed' => 'Assinado',
    'Refused' => 'Recusado',
    'Pending' => 'Pendente',
    'All Statuses' => 'Todos os Status',
    'From date' => 'De',
    'To date' => 'Até',
    'Document ID' => 'ID do Documento',
    'Document Name' => 'Nome do Documento',
    'Submission' => 'Envio',
    'Signer' => 'Signatário',
    'Signed At' => 'Assinado em',
    'Document Details' => 'Detalhes do Documento',
    'Open in Autentique' => 'Abrir no Autentique',
    'Resend Link' => 'Reenviar Link',
    'Digital Signature' => 'Assinatura Digital',
    'Digital Signatures' => 'Assinaturas Digitais',
    'Signature URL' => 'URL de Assinatura',
    'Signature Status' => 'Status da Assinatura',
    'Create Document' => 'Criar Documento',
    'Document created successfully.' => 'Documento criado com sucesso.',
    'Document deleted successfully.' => 'Documento excluído com sucesso.',
    'Failed to create document.' => 'Falha ao criar documento.',
    'Failed to resend signature link.' => 'Falha ao reenviar link de assinatura.',
    'Signature link resent successfully.' => 'Link de assinatura reenviado com sucesso.',
    'Reminder: Please sign the document "%s"' => 'Lembrete: Por favor, assine o documento "%s"',

    // Forms
    'Form' => 'Formulário',
    'Form Name' => 'Nome do Formulário',
    'Form ID' => 'ID do Formulário',
    'Form Status' => 'Status do Formulário',
    'Form Settings' => 'Configurações do Formulário',
    'Form Fields' => 'Campos do Formulário',
    'Form Builder' => 'Construtor de Formulário',
    'Create Form' => 'Criar Formulário',
    'Edit Form' => 'Editar Formulário',
    'Delete Form' => 'Excluir Formulário',
    'Duplicate Form' => 'Duplicar Formulário',
    'Form created successfully.' => 'Formulário criado com sucesso.',
    'Form updated successfully.' => 'Formulário atualizado com sucesso.',
    'Form deleted successfully.' => 'Formulário excluído com sucesso.',
    'Form duplicated successfully.' => 'Formulário duplicado com sucesso.',
    'Form not found.' => 'Formulário não encontrado.',
    'Form status updated.' => 'Status do formulário atualizado.',
    'Please select a form to display.' => 'Por favor, selecione um formulário para exibir.',
    'No forms found.' => 'Nenhum formulário encontrado.',
    'Draft' => 'Rascunho',
    'Published' => 'Publicado',
    'Archived' => 'Arquivado',

    // Submissions
    'Submission ID' => 'ID do Envio',
    'Submission Data' => 'Dados do Envio',
    'Submission Date' => 'Data do Envio',
    'Submission Status' => 'Status do Envio',
    'View Submission' => 'Ver Envio',
    'Delete Submission' => 'Excluir Envio',
    'Export Submissions' => 'Exportar Envios',
    'Submission deleted successfully.' => 'Envio excluído com sucesso.',
    '%d submissions deleted successfully.' => '%d envios excluídos com sucesso.',
    'No submissions found.' => 'Nenhum envio encontrado.',
    'Completed' => 'Concluído',
    'Processing' => 'Processando',
    'Failed' => 'Falhou',
    'Pending Signature' => 'Pendente de Assinatura',

    // Analytics
    'Total Submissions' => 'Total de Envios',
    'Today' => 'Hoje',
    'This Week' => 'Esta Semana',
    'This Month' => 'Este Mês',
    'Last 30 Days' => 'Últimos 30 Dias',
    'Last 90 Days' => 'Últimos 90 Dias',
    'Custom Range' => 'Período Personalizado',
    'Conversion Rate' => 'Taxa de Conversão',
    'Average Time' => 'Tempo Médio',
    'Top Forms' => 'Formulários Principais',
    'Recent Activity' => 'Atividade Recente',
    'Trends' => 'Tendências',
    'Statistics' => 'Estatísticas',
    'Chart' => 'Gráfico',
    'Table' => 'Tabela',
    'Line Chart' => 'Gráfico de Linha',
    'Bar Chart' => 'Gráfico de Barras',
    'Pie Chart' => 'Gráfico de Pizza',

    // Reports
    'Reports' => 'Relatórios',
    'Report' => 'Relatório',
    'Generate Report' => 'Gerar Relatório',
    'Schedule Report' => 'Agendar Relatório',
    'Report Template' => 'Modelo de Relatório',
    'Report Settings' => 'Configurações do Relatório',
    'Export Report' => 'Exportar Relatório',
    'PDF Report' => 'Relatório em PDF',
    'Excel Report' => 'Relatório em Excel',
    'CSV Export' => 'Exportar CSV',
    'Scheduled Reports' => 'Relatórios Agendados',
    'Report History' => 'Histórico de Relatórios',
    'Daily' => 'Diário',
    'Weekly' => 'Semanal',
    'Monthly' => 'Mensal',
    'Quarterly' => 'Trimestral',
    'Yearly' => 'Anual',

    // Automation
    'Automation' => 'Automação',
    'Automations' => 'Automações',
    'Workflow' => 'Fluxo de Trabalho',
    'Workflows' => 'Fluxos de Trabalho',
    'Create Workflow' => 'Criar Fluxo de Trabalho',
    'Edit Workflow' => 'Editar Fluxo de Trabalho',
    'Workflow Name' => 'Nome do Fluxo',
    'Workflow Status' => 'Status do Fluxo',
    'Triggers' => 'Gatilhos',
    'Trigger' => 'Gatilho',
    'Add Trigger' => 'Adicionar Gatilho',
    'Actions' => 'Ações',
    'Action' => 'Ação',
    'Add Action' => 'Adicionar Ação',
    'Conditions' => 'Condições',
    'Condition' => 'Condição',
    'Add Condition' => 'Adicionar Condição',
    'If' => 'Se',
    'Then' => 'Então',
    'Else' => 'Senão',
    'And' => 'E',
    'Or' => 'Ou',
    'Equals' => 'Igual a',
    'Not Equals' => 'Diferente de',
    'Contains' => 'Contém',
    'Not Contains' => 'Não Contém',
    'Greater Than' => 'Maior que',
    'Less Than' => 'Menor que',
    'Is Empty' => 'Está Vazio',
    'Is Not Empty' => 'Não Está Vazio',
    'Form Submission' => 'Envio de Formulário',
    'Send Email' => 'Enviar E-mail',
    'Create Document' => 'Criar Documento',
    'Update Field' => 'Atualizar Campo',
    'Webhook' => 'Webhook',
    'HTTP Request' => 'Requisição HTTP',
    'Delay' => 'Atraso',
    'Wait' => 'Aguardar',

    // SSO
    'Single Sign-On' => 'Login Único (SSO)',
    'SSO' => 'SSO',
    'SSO Settings' => 'Configurações de SSO',
    'SSO Provider' => 'Provedor de SSO',
    'SSO Providers' => 'Provedores de SSO',
    'OAuth 2.0' => 'OAuth 2.0',
    'SAML 2.0' => 'SAML 2.0',
    'LDAP' => 'LDAP',
    'OpenID Connect' => 'OpenID Connect',
    'Google' => 'Google',
    'Microsoft' => 'Microsoft',
    'Okta' => 'Okta',
    'Auth0' => 'Auth0',
    'Client ID' => 'ID do Cliente',
    'Client Secret' => 'Segredo do Cliente',
    'Redirect URI' => 'URI de Redirecionamento',
    'SSO Error:' => 'Erro de SSO:',
    'Or sign in with:' => 'Ou entre com:',
    'Login with %s' => 'Entrar com %s',
    'SSO session expired.' => 'Sessão SSO expirada.',
    'SSO authentication failed.' => 'Autenticação SSO falhou.',

    // Payments
    'Payments' => 'Pagamentos',
    'Payment' => 'Pagamento',
    'Payment Settings' => 'Configurações de Pagamento',
    'Payment Method' => 'Método de Pagamento',
    'Payment Methods' => 'Métodos de Pagamento',
    'Credit Card' => 'Cartão de Crédito',
    'Debit Card' => 'Cartão de Débito',
    'PayPal' => 'PayPal',
    'Stripe' => 'Stripe',
    'WooCommerce' => 'WooCommerce',
    'Amount' => 'Valor',
    'Currency' => 'Moeda',
    'Price' => 'Preço',
    'Total' => 'Total',
    'Subtotal' => 'Subtotal',
    'Tax' => 'Imposto',
    'Discount' => 'Desconto',
    'Coupon' => 'Cupom',
    'Apply Coupon' => 'Aplicar Cupom',
    'Payment Successful' => 'Pagamento Realizado',
    'Payment Failed' => 'Pagamento Falhou',
    'Payment Pending' => 'Pagamento Pendente',
    'Refund' => 'Reembolso',
    'Refunded' => 'Reembolsado',
    'Invoice' => 'Fatura',
    'Receipt' => 'Recibo',
    'Transaction ID' => 'ID da Transação',
    'Subscription' => 'Assinatura',
    'Subscriptions' => 'Assinaturas',
    'Subscribe' => 'Assinar',
    'Unsubscribe' => 'Cancelar Assinatura',

    // Security
    'Security' => 'Segurança',
    'Security Settings' => 'Configurações de Segurança',
    'Two-Factor Authentication' => 'Autenticação de Dois Fatores',
    '2FA' => '2FA',
    'Enable 2FA' => 'Habilitar 2FA',
    'Disable 2FA' => 'Desabilitar 2FA',
    'Verification Code' => 'Código de Verificação',
    'Enter verification code' => 'Digite o código de verificação',
    'Backup Codes' => 'Códigos de Backup',
    'Generate Backup Codes' => 'Gerar Códigos de Backup',
    'GDPR Compliance' => 'Conformidade GDPR',
    'Data Export' => 'Exportação de Dados',
    'Data Deletion' => 'Exclusão de Dados',
    'Privacy Policy' => 'Política de Privacidade',
    'Terms of Service' => 'Termos de Serviço',
    'Consent' => 'Consentimento',
    'Audit Log' => 'Log de Auditoria',
    'Audit Logs' => 'Logs de Auditoria',
    'Access Control' => 'Controle de Acesso',
    'Permissions' => 'Permissões',
    'Role' => 'Função',
    'Roles' => 'Funções',
    'Administrator' => 'Administrador',
    'Editor' => 'Editor',
    'Author' => 'Autor',
    'Contributor' => 'Contribuidor',
    'Subscriber' => 'Assinante',
    'IP Whitelist' => 'Lista de IPs Permitidos',
    'IP Blacklist' => 'Lista de IPs Bloqueados',
    'Rate Limiting' => 'Limitação de Taxa',
    'Spam Protection' => 'Proteção contra Spam',
    'reCAPTCHA' => 'reCAPTCHA',
    'Honeypot' => 'Honeypot',

    // Marketplace
    'Marketplace' => 'Marketplace',
    'Extensions' => 'Extensões',
    'Extension' => 'Extensão',
    'Install' => 'Instalar',
    'Uninstall' => 'Desinstalar',
    'Activate' => 'Ativar',
    'Deactivate' => 'Desativar',
    'Update' => 'Atualizar',
    'Update Available' => 'Atualização Disponível',
    'Installed' => 'Instalado',
    'Not Installed' => 'Não Instalado',
    'Extension settings page.' => 'Página de configurações da extensão.',
    'Browse Extensions' => 'Explorar Extensões',
    'My Extensions' => 'Minhas Extensões',
    'Featured' => 'Em Destaque',
    'Popular' => 'Popular',
    'New' => 'Novo',
    'Category' => 'Categoria',
    'Categories' => 'Categorias',
    'Rating' => 'Avaliação',
    'Reviews' => 'Avaliações',
    'Author' => 'Autor',
    'Last Updated' => 'Última Atualização',

    // PWA
    'Progressive Web App' => 'Aplicativo Web Progressivo',
    'PWA' => 'PWA',
    'PWA Settings' => 'Configurações do PWA',
    'App Name' => 'Nome do Aplicativo',
    'Short Name' => 'Nome Curto',
    'Description' => 'Descrição',
    'App Icon' => 'Ícone do Aplicativo',
    'Theme Color' => 'Cor do Tema',
    'Background Color' => 'Cor de Fundo',
    'Display Mode' => 'Modo de Exibição',
    'Standalone' => 'Standalone',
    'Fullscreen' => 'Tela Cheia',
    'Minimal UI' => 'Interface Mínima',
    'Browser' => 'Navegador',
    'Orientation' => 'Orientação',
    'Portrait' => 'Retrato',
    'Landscape' => 'Paisagem',
    'Any' => 'Qualquer',
    'Offline Support' => 'Suporte Offline',
    'Push Notifications' => 'Notificações Push',
    'Install Prompt' => 'Solicitação de Instalação',
    'Service Worker' => 'Service Worker',
    'Cache Strategy' => 'Estratégia de Cache',
    'Network First' => 'Rede Primeiro',
    'Cache First' => 'Cache Primeiro',
    'Stale While Revalidate' => 'Obsoleto Enquanto Revalida',

    // Integrations
    'Integrations' => 'Integrações',
    'Integration' => 'Integração',
    'Connected' => 'Conectado',
    'Disconnected' => 'Desconectado',
    'Connect' => 'Conectar',
    'Disconnect' => 'Desconectar',
    'Configure' => 'Configurar',
    'API Key' => 'Chave API',
    'API Secret' => 'Segredo API',
    'API Configured' => 'API Configurada',
    'API Not Configured' => 'API Não Configurada',
    'Enter your API key below to enable digital signatures.' => 'Digite sua chave API abaixo para habilitar assinaturas digitais.',
    '%s integration is enabled but not properly configured.' => 'A integração %s está habilitada mas não está configurada corretamente.',
    'Webhook URL' => 'URL do Webhook',
    'Test Connection' => 'Testar Conexão',
    'Connection successful.' => 'Conexão bem-sucedida.',
    'Connection failed.' => 'Conexão falhou.',
    'Salesforce' => 'Salesforce',
    'HubSpot' => 'HubSpot',
    'Google Sheets' => 'Google Sheets',
    'Zapier' => 'Zapier',
    'Slack' => 'Slack',
    'Microsoft Teams' => 'Microsoft Teams',
    'Mailchimp' => 'Mailchimp',
    'SendGrid' => 'SendGrid',
    'Twilio' => 'Twilio',
    'AWS S3' => 'AWS S3',
    'Google Drive' => 'Google Drive',
    'Dropbox' => 'Dropbox',

    // Notifications
    'Notifications' => 'Notificações',
    'Notification' => 'Notificação',
    'Email Notifications' => 'Notificações por E-mail',
    'SMS Notifications' => 'Notificações por SMS',
    'Push Notifications' => 'Notificações Push',
    'Slack Notifications' => 'Notificações no Slack',
    'Send Notification' => 'Enviar Notificação',
    'Notification Settings' => 'Configurações de Notificação',
    'Notification Template' => 'Modelo de Notificação',
    'Email Template' => 'Modelo de E-mail',
    'Subject' => 'Assunto',
    'Message' => 'Mensagem',
    'Recipients' => 'Destinatários',
    'Send Test' => 'Enviar Teste',
    'Test email sent successfully.' => 'E-mail de teste enviado com sucesso.',

    // AI
    'AI Features' => 'Recursos de IA',
    'Artificial Intelligence' => 'Inteligência Artificial',
    'AI Settings' => 'Configurações de IA',
    'Smart Validation' => 'Validação Inteligente',
    'Spam Detection' => 'Detecção de Spam',
    'Auto-Suggestions' => 'Sugestões Automáticas',
    'Sentiment Analysis' => 'Análise de Sentimento',
    'Document Classification' => 'Classificação de Documentos',
    'OpenAI' => 'OpenAI',
    'OpenAI API Key' => 'Chave API OpenAI',
    'GPT Model' => 'Modelo GPT',
    'AI Provider' => 'Provedor de IA',
    'Local AI' => 'IA Local',

    // Multi-site
    'Network' => 'Rede',
    'Network Settings' => 'Configurações da Rede',
    'Network Dashboard' => 'Painel da Rede',
    'Sites' => 'Sites',
    'Site' => 'Site',
    'All Sites' => 'Todos os Sites',
    'Network License' => 'Licença da Rede',
    'FormFlow Pro network license not configured. Some features may be limited.' => 'Licença de rede do FormFlow Pro não configurada. Alguns recursos podem estar limitados.',
    'License limit reached: %1$d of %2$d sites active.' => 'Limite de licença atingido: %1$d de %2$d sites ativos.',

    // Field Types
    'Text' => 'Texto',
    'Text Area' => 'Área de Texto',
    'Number' => 'Número',
    'Phone' => 'Telefone',
    'URL' => 'URL',
    'Password' => 'Senha',
    'Hidden' => 'Oculto',
    'Select' => 'Seleção',
    'Multi-Select' => 'Seleção Múltipla',
    'Checkbox' => 'Caixa de Seleção',
    'Radio' => 'Botão de Opção',
    'Date' => 'Data',
    'Time' => 'Hora',
    'Date & Time' => 'Data e Hora',
    'File Upload' => 'Upload de Arquivo',
    'Image Upload' => 'Upload de Imagem',
    'Signature' => 'Assinatura',
    'Rating' => 'Avaliação',
    'Range' => 'Intervalo',
    'Color' => 'Cor',
    'Address' => 'Endereço',
    'Country' => 'País',
    'State' => 'Estado',
    'City' => 'Cidade',
    'ZIP Code' => 'CEP',
    'Credit Card' => 'Cartão de Crédito',
    'CAPTCHA' => 'CAPTCHA',
    'HTML' => 'HTML',
    'Divider' => 'Divisor',
    'Page Break' => 'Quebra de Página',
    'Repeater' => 'Repetidor',
    'Group' => 'Grupo',

    // Validation
    'Required' => 'Obrigatório',
    'Optional' => 'Opcional',
    'This field is required.' => 'Este campo é obrigatório.',
    'Please enter a valid email address.' => 'Por favor, digite um endereço de e-mail válido.',
    'Please enter a valid phone number.' => 'Por favor, digite um número de telefone válido.',
    'Please enter a valid URL.' => 'Por favor, digite uma URL válida.',
    'Please enter a valid number.' => 'Por favor, digite um número válido.',
    'Please enter a valid date.' => 'Por favor, digite uma data válida.',
    'Minimum length is %d characters.' => 'O comprimento mínimo é %d caracteres.',
    'Maximum length is %d characters.' => 'O comprimento máximo é %d caracteres.',
    'Minimum value is %d.' => 'O valor mínimo é %d.',
    'Maximum value is %d.' => 'O valor máximo é %d.',
    'Please select at least one option.' => 'Por favor, selecione pelo menos uma opção.',
    'Please select at most %d options.' => 'Por favor, selecione no máximo %d opções.',
    'File size must not exceed %s.' => 'O tamanho do arquivo não deve exceder %s.',
    'Invalid file type.' => 'Tipo de arquivo inválido.',
    'Allowed file types: %s' => 'Tipos de arquivo permitidos: %s',

    // UX
    'Dark Mode' => 'Modo Escuro',
    'Light Mode' => 'Modo Claro',
    'Auto' => 'Automático',
    'Compact Mode' => 'Modo Compacto',
    'Disable Animations' => 'Desativar Animações',
    'High Contrast' => 'Alto Contraste',
    'Keyboard Shortcuts' => 'Atalhos de Teclado',
    'Show Shortcuts' => 'Mostrar Atalhos',
    'Quick Actions' => 'Ações Rápidas',
    'Command Palette' => 'Paleta de Comandos',
    'Press %s to open command palette' => 'Pressione %s para abrir a paleta de comandos',
    'Autosave' => 'Salvamento Automático',
    'Autosave enabled' => 'Salvamento automático habilitado',
    'Draft saved' => 'Rascunho salvo',
    'Changes saved' => 'Alterações salvas',
    'Unsaved changes' => 'Alterações não salvas',
    'Saving...' => 'Salvando...',
    'Bulk Actions' => 'Ações em Massa',
    'Select All' => 'Selecionar Todos',
    'Deselect All' => 'Desmarcar Todos',
    'Items per page' => 'Itens por página',
    'Showing %1$d to %2$d of %3$d items' => 'Mostrando %1$d a %2$d de %3$d itens',

    // Errors & Messages
    'An error occurred. Please try again.' => 'Ocorreu um erro. Por favor, tente novamente.',
    'Operation completed successfully.' => 'Operação concluída com sucesso.',
    'Are you sure you want to delete this?' => 'Tem certeza que deseja excluir isso?',
    'This action cannot be undone.' => 'Esta ação não pode ser desfeita.',
    'Access denied.' => 'Acesso negado.',
    'Permission denied.' => 'Permissão negada.',
    'Invalid request.' => 'Requisição inválida.',
    'Session expired.' => 'Sessão expirada.',
    'Please log in again.' => 'Por favor, faça login novamente.',
    'Network error. Please check your connection.' => 'Erro de rede. Por favor, verifique sua conexão.',
    'Server error. Please try again later.' => 'Erro no servidor. Por favor, tente novamente mais tarde.',
    'Invalid data provided.' => 'Dados inválidos fornecidos.',
    'Missing required fields.' => 'Campos obrigatórios faltando.',
    'Duplicate entry.' => 'Entrada duplicada.',
    'Not found.' => 'Não encontrado.',
    'Already exists.' => 'Já existe.',
    'Limit exceeded.' => 'Limite excedido.',
    'Rate limit exceeded. Please wait.' => 'Limite de requisições excedido. Por favor, aguarde.',

    // Requirements
    'Plugin Activation Error' => 'Erro de Ativação do Plugin',
    'FormFlow Pro requires PHP 8.0 or higher.' => 'FormFlow Pro requer PHP 8.0 ou superior.',
    'FormFlow Pro requires WordPress 6.0 or higher.' => 'FormFlow Pro requer WordPress 6.0 ou superior.',
    'FormFlow Pro requires the %s PHP extension.' => 'FormFlow Pro requer a extensão PHP %s.',
    '"%1$s" requires "%2$s" to be installed and activated.' => '"%1$s" requer que "%2$s" esteja instalado e ativado.',
    '"%1$s" requires "%2$s" version %3$s or greater.' => '"%1$s" requer "%2$s" versão %3$s ou superior.',
    'Elementor' => 'Elementor',
    'PHP' => 'PHP',
    'WordPress' => 'WordPress',

    // Time
    'seconds' => 'segundos',
    'minutes' => 'minutos',
    'hours' => 'horas',
    'days' => 'dias',
    'weeks' => 'semanas',
    'months' => 'meses',
    'years' => 'anos',
    '%d second' => '%d segundo',
    '%d seconds' => '%d segundos',
    '%d minute' => '%d minuto',
    '%d minutes' => '%d minutos',
    '%d hour' => '%d hora',
    '%d hours' => '%d horas',
    '%d day' => '%d dia',
    '%d days' => '%d dias',
    '%d week' => '%d semana',
    '%d weeks' => '%d semanas',
    '%d month' => '%d mês',
    '%d months' => '%d meses',
    '%d year' => '%d ano',
    '%d years' => '%d anos',
    'ago' => 'atrás',
    'from now' => 'a partir de agora',
    'Just now' => 'Agora mesmo',

    // Misc
    'ID' => 'ID',
    'Type' => 'Tipo',
    'Value' => 'Valor',
    'Label' => 'Rótulo',
    'Placeholder' => 'Texto de exemplo',
    'Default Value' => 'Valor Padrão',
    'Options' => 'Opções',
    'Option' => 'Opção',
    'Add Option' => 'Adicionar Opção',
    'Remove' => 'Remover',
    'Move Up' => 'Mover para Cima',
    'Move Down' => 'Mover para Baixo',
    'Preview' => 'Pré-visualizar',
    'Code' => 'Código',
    'Shortcode' => 'Shortcode',
    'Copy Shortcode' => 'Copiar Shortcode',
    'Shortcode copied!' => 'Shortcode copiado!',
    'Embed' => 'Incorporar',
    'Embed Code' => 'Código de Incorporação',
    'Width' => 'Largura',
    'Height' => 'Altura',
    'Size' => 'Tamanho',
    'Small' => 'Pequeno',
    'Medium' => 'Médio',
    'Large' => 'Grande',
    'Custom' => 'Personalizado',
    'Default' => 'Padrão',
    'Advanced' => 'Avançado',
    'Basic' => 'Básico',
    'General' => 'Geral',
    'Appearance' => 'Aparência',
    'Behavior' => 'Comportamento',
    'Layout' => 'Layout',
    'Style' => 'Estilo',
    'CSS' => 'CSS',
    'CSS Classes' => 'Classes CSS',
    'Custom CSS' => 'CSS Personalizado',
    'JavaScript' => 'JavaScript',
    'Custom JavaScript' => 'JavaScript Personalizado',
    'HTML ID' => 'ID HTML',
    'Attributes' => 'Atributos',
    'Data Attributes' => 'Atributos de Dados',
];

/**
 * Extract translation strings from PHP files
 */
function extract_strings($directory) {
    $strings = [];
    $patterns = [
        '/__\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*[\'"]formflow-pro[\'"]\s*)?\)/',
        '/_e\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*[\'"]formflow-pro[\'"]\s*)?\)/',
        '/esc_html__\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*[\'"]formflow-pro[\'"]\s*)?\)/',
        '/esc_html_e\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*[\'"]formflow-pro[\'"]\s*)?\)/',
        '/esc_attr__\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*[\'"]formflow-pro[\'"]\s*)?\)/',
        '/esc_attr_e\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*[\'"]formflow-pro[\'"]\s*)?\)/',
        '/_n\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*/',
        '/_x\s*\(\s*[\'"]([^\'"]+)[\'"]\s*,\s*[\'"]([^\'"]+)[\'"]\s*/',
    ];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        // Skip vendor directory
        if (strpos($file->getPathname(), '/vendor/') !== false) {
            continue;
        }

        $content = file_get_contents($file->getPathname());

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[1] as $string) {
                    $strings[$string] = true;
                }
                if (isset($matches[2])) {
                    foreach ($matches[2] as $string) {
                        $strings[$string] = true;
                    }
                }
            }
        }
    }

    return array_keys($strings);
}

/**
 * Generate POT file
 */
function generate_pot($strings, $output_file) {
    $pot = <<<POT
# Translation Template for FormFlow Pro Enterprise
# Copyright (C) 2025 FormFlow Pro Team
# This file is distributed under the same license as the FormFlow Pro Enterprise package.
#
# FIRST AUTHOR <EMAIL@ADDRESS>, YEAR.
#
#, fuzzy
msgid ""
msgstr ""
"Project-Id-Version: FormFlow Pro Enterprise 2.0.0\\n"
"Report-Msgid-Bugs-To: https://formflowpro.com/support\\n"
"POT-Creation-Date: " . date('Y-m-d H:i+0000') . "\\n"
"PO-Revision-Date: YEAR-MO-DA HO:MI+ZONE\\n"
"Last-Translator: FULL NAME <EMAIL@ADDRESS>\\n"
"Language-Team: LANGUAGE <LL@li.org>\\n"
"Language: \\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: nplurals=2; plural=(n != 1);\\n"

POT;

    sort($strings);

    foreach ($strings as $string) {
        $escaped = addcslashes($string, '"\\');
        $pot .= "\nmsgid \"{$escaped}\"\n";
        $pot .= "msgstr \"\"\n";
    }

    file_put_contents($output_file, $pot);
    echo "Generated POT file: $output_file\n";
    echo "Total strings: " . count($strings) . "\n";
}

/**
 * Generate PO file with translations
 */
function generate_po($strings, $translations, $output_file, $language = 'pt_BR') {
    $language_info = [
        'pt_BR' => [
            'name' => 'Portuguese (Brazil)',
            'plural' => 'nplurals=2; plural=(n > 1);',
        ],
    ];

    $info = $language_info[$language] ?? ['name' => $language, 'plural' => 'nplurals=2; plural=(n != 1);'];

    $po = <<<PO
# {$info['name']} translation for FormFlow Pro Enterprise
# Copyright (C) 2025 FormFlow Pro Team
# This file is distributed under the same license as the FormFlow Pro Enterprise package.
#
msgid ""
msgstr ""
"Project-Id-Version: FormFlow Pro Enterprise 2.0.0\\n"
"Report-Msgid-Bugs-To: https://formflowpro.com/support\\n"
"POT-Creation-Date: " . date('Y-m-d H:i+0000') . "\\n"
"PO-Revision-Date: " . date('Y-m-d H:i+0000') . "\\n"
"Last-Translator: FormFlow Pro Team <support@formflowpro.com>\\n"
"Language-Team: {$info['name']} <support@formflowpro.com>\\n"
"Language: {$language}\\n"
"MIME-Version: 1.0\\n"
"Content-Type: text/plain; charset=UTF-8\\n"
"Content-Transfer-Encoding: 8bit\\n"
"Plural-Forms: {$info['plural']}\\n"
"X-Generator: FormFlow Pro Translation Generator\\n"

PO;

    sort($strings);
    $translated = 0;
    $untranslated = 0;

    foreach ($strings as $string) {
        $escaped_id = addcslashes($string, '"\\');
        $translation = $translations[$string] ?? '';
        $escaped_str = addcslashes($translation, '"\\');

        if (!empty($translation)) {
            $translated++;
        } else {
            $untranslated++;
        }

        $po .= "\nmsgid \"{$escaped_id}\"\n";
        $po .= "msgstr \"{$escaped_str}\"\n";
    }

    file_put_contents($output_file, $po);
    echo "Generated PO file: $output_file\n";
    echo "Translated: $translated / " . count($strings) . "\n";
    echo "Untranslated: $untranslated\n";
}

// Main execution
$base_dir = __DIR__;
$includes_dir = $base_dir . '/includes';
$languages_dir = $base_dir . '/languages';

if (!is_dir($languages_dir)) {
    mkdir($languages_dir, 0755, true);
}

echo "Extracting translation strings from: $includes_dir\n\n";

// Extract strings
$strings = extract_strings($includes_dir);

// Also add strings from main plugin file
$main_file = $base_dir . '/formflow-pro.php';
if (file_exists($main_file)) {
    $content = file_get_contents($main_file);
    $patterns = [
        '/__\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*[\'"]formflow-pro[\'"]\s*)?\)/',
        '/esc_html__\s*\(\s*[\'"]([^\'"]+)[\'"]\s*(?:,\s*[\'"]formflow-pro[\'"]\s*)?\)/',
    ];
    foreach ($patterns as $pattern) {
        if (preg_match_all($pattern, $content, $matches)) {
            foreach ($matches[1] as $string) {
                $strings[] = $string;
            }
        }
    }
}

// Remove duplicates
$strings = array_unique($strings);

echo "Found " . count($strings) . " unique strings\n\n";

// Merge with translation dictionary - add any missing entries
foreach (array_keys($translations) as $key) {
    if (!in_array($key, $strings)) {
        $strings[] = $key;
    }
}

// Generate files
generate_pot($strings, $languages_dir . '/formflow-pro.pot');
echo "\n";
generate_po($strings, $translations, $languages_dir . '/formflow-pro-pt_BR.po', 'pt_BR');

echo "\nDone! Run compile-translations.php to generate .mo files.\n";
