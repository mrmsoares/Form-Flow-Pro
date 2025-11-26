# FormFlow Pro Enterprise

**Version:** 3.0.0
**Requires at least:** WordPress 6.0
**Requires PHP:** 8.1+
**License:** GPL-2.0+
**Status:** 🚀 **Enterprise Ready (100%)**

![Tests](https://img.shields.io/badge/tests-100%2B%20passed-success)
![Coverage](https://img.shields.io/badge/coverage-enabled-success)
![PHP](https://img.shields.io/badge/PHP-8.1%20%7C%208.2%20%7C%208.3-blue)
![PHPStan](https://img.shields.io/badge/PHPStan-level%205-brightgreen)
![Autentique](https://img.shields.io/badge/Autentique-100%25%20compliant-green)
![i18n](https://img.shields.io/badge/i18n-pt__BR-blue)
![Queue](https://img.shields.io/badge/Queue-optimized-brightgreen)
![SSO](https://img.shields.io/badge/SSO-Enterprise-blue)
![D3.js](https://img.shields.io/badge/D3.js-v7-orange)

FormFlow Pro Enterprise é um plugin WordPress de classe enterprise para processamento automatizado de formulários do Elementor. Oferece geração inteligente de PDFs, integração nativa com Autentique para assinaturas digitais, sistema avançado de queue, analytics em tempo real com D3.js, Visual Automation Builder, Enterprise SSO, API Marketplace e 54 melhorias de UX premium.

## 📸 Screenshots

### Dashboard
> Interface principal com visão geral de submissions, forms e estatísticas.

### Autentique Documents
> Gerencie documentos enviados para assinatura digital com DataTables e filtros.

### Settings - Autentique Tab
> Configuração completa da integração Autentique com:
> - Status indicator (configurado/não configurado)
> - Quick stats (Total, Pendentes, Assinados, Recusados)
> - API Key configuration com toggle de visibilidade
> - Webhook URL com botão de cópia
> - Document settings (auto-send, reminders, default message)
> - Getting started guide

### Queue Monitor
> Visualize e monitore jobs em processamento com estatísticas em tempo real.

---

## 🎯 Principais Diferenciais

- ✅ **Native Autentique Integration** - Única solução com integração nativa 100% conforme documentação oficial
- ✅ **Autentique Admin UI** - Interface completa para gerenciar documentos, status e reenvio de links
- ✅ **D3.js Advanced Reporting** - 12 tipos de gráficos interativos com relatórios PDF/Excel agendados
- ✅ **Visual Automation Builder** - Construtor visual de workflows com drag-and-drop
- ✅ **Enterprise SSO** - Google, Microsoft, Okta, Auth0 e SAML 2.0
- ✅ **API Marketplace** - Integrações com Slack, Salesforce, HubSpot, Zapier e webhooks customizados
- ✅ **Enterprise Performance** - 90+ Core Web Vitals score (vs 65-72 competitors)
- ✅ **Internationalization** - Tradução completa pt_BR (400+ strings)
- ✅ **Real-Time Analytics** - Dashboard com métricas em tempo real via D3.js
- ✅ **Optimized Queue System** - Processamento assíncrono com exponential backoff e dead letter queue
- ✅ **White-Label Ready** - Personalização total para agências

## ✨ Novidades v3.0.0 (Current Release)

### 📊 Advanced Reporting Module (V2.3.0)
- **12 Chart Types:** Line, Bar, Pie, Donut, Area, Scatter, Heatmap, Funnel, Gauge, Radial Bar, Treemap, Sankey
- **D3.js v7 Integration:** Visualizações interativas de alta performance
- **Report Generator:** PDF, Excel, CSV, JSON e HTML exports
- **Scheduled Reports:** Daily, weekly, monthly, quarterly com envio por email
- **KPI Dashboards:** Widgets customizáveis com tendências
- **Report History:** Histórico completo com download e cleanup automático

### 🔧 Visual Automation Builder (V3.0.0)
- **Drag-and-Drop Interface:** Construtor visual de workflows intuitivo
- **13 Node Types:** Start, End, Condition, Loop, Delay, Set Variable, Transform, Send Email, Send SMS, HTTP Request, Database Query, Create PDF, Send Signature
- **Visual Connections:** Bezier curves para conexões entre nodes
- **Property Panel:** Configuração detalhada de cada node
- **Keyboard Shortcuts:** Undo/Redo, Delete, Select All, Copy/Paste
- **Real-time Validation:** Validação de workflow em tempo real
- **Execution History:** Log completo de execuções

### 🔐 Enterprise SSO Manager (V3.0.0)
- **OAuth 2.0 Providers:** Google, Microsoft, Okta, Auth0
- **SAML 2.0 Support:** Integração com IdPs corporativos
- **User Provisioning:** Criação automática de usuários
- **Provider Linking:** Vincular múltiplos providers por usuário
- **Token Management:** Refresh tokens, validação e expiração
- **State Tokens:** Proteção CSRF para fluxos OAuth

### 🏪 API Marketplace (V3.0.0)
- **Pre-built Integrations:** Slack, Salesforce, HubSpot, Zapier, Google Sheets
- **Custom Integrations:** Registre suas próprias integrações
- **Webhook System:** Webhooks por evento/formulário
- **API Credentials:** Geração e gerenciamento de API keys
- **Integration Logs:** Histórico detalhado de execuções
- **Category Filters:** Communication, CRM, Storage, Analytics, Automation

---

## ✨ Novidades v2.1.0

### 🎛️ Enhanced Settings UX
- **Status Banner:** Indicator visual mostrando se API está configurada
- **Quick Stats Dashboard:** Total, Pendentes, Assinados, Recusados em cards
- **API Key Security:** Campo password com toggle de visibilidade
- **Webhook Configuration:** Seção dedicada com botão de cópia
- **Document Settings:** Auto-send, reminders e default message
- **Getting Started Guide:** Passo a passo integrado na interface

### ⚡ Queue System Optimizations
- **Configurable Batch Size:** Tamanho do batch via settings
- **Atomic Job Claiming:** FOR UPDATE SKIP LOCKED para evitar duplicatas
- **Exponential Backoff:** Retries com delays crescentes (60s, 5min, 15min)
- **Dead Letter Queue:** Jobs permanentemente falhados são isolados
- **Auto-Healing:** Reset automático de jobs travados (timeout 5min)
- **Queue Statistics:** Método get_stats() para monitoramento

### 🔧 Technical Improvements
- **Option Name Consistency:** Unificado formflow_autentique_api_key
- **Settings JS Loading:** Script carregado corretamente na página settings
- **New Settings Options:** reminder_enabled, document_message

---

## ✨ Novidades v2.0.0 (Phase 10)

### 🎨 Admin UI Autentique (100% Completo)
- **Dashboard Completo:** Visualize todos os documentos Autentique em uma interface intuitiva
- **Estatísticas em Cards:** Total, Pendentes, Assinados e Recusados com ícones e cores
- **DataTable Avançada:** Busca, filtros, paginação e ordenação
- **Visualização de Detalhes:** Modal com informações completas do documento
- **Reenvio de Links:** Funcionalidade para reenviar links de assinatura pendentes
- **Integração Direta:** Link para abrir documentos no Autentique
- **Responsivo:** Interface otimizada para desktop e mobile

### 🌍 Internacionalização (i18n)
- **Tradução Completa pt_BR:** 400+ strings traduzidas
- **Arquivos Incluídos:**
  - `formflow-pro.pot` - Template de tradução
  - `formflow-pro-pt_BR.po` - Tradução português brasileiro
  - `formflow-pro-pt_BR.mo` - Arquivo compilado
- **Suporte Multi-idioma:** Estrutura pronta para adicionar novos idiomas

### 🔧 Melhorias Técnicas
- **Autentique 100% Compliant:** Implementação conforme documentação oficial
- **Multipart Upload:** Upload de PDFs via GraphQL multipart/form-data
- **Custom Cron Schedules:** Intervalos personalizados (5 minutos, semanal)
- **Database Table:** Nova tabela `formflow_autentique_documents` para tracking
- **AJAX Handlers:** API completa para operações da Admin UI
- **CSS Personalizado:** Estilos exclusivos para página Autentique

## 📊 Status do Projeto

### ✅ Fase 1: Planejamento & Arquitetura (Completa - 255+ páginas)
- ✅ Product Requirements Document (PRD)
- ✅ User Research Report
- ✅ Competitive Analysis
- ✅ Performance Requirements
- ✅ Architecture Overview
- ✅ Design System
- ✅ Database Schema

### ✅ Fase 2: Fundação & Core (Completa)
- ✅ **2.1:** Plugin Skeleton
- ✅ **2.1:** Composer & Webpack setup
- ✅ **2.1:** Admin interface básica (4 páginas)
- ✅ **2.2:** Database Manager com sistema de migrations
- ✅ **2.2:** Migration v2.0.0 (10 tabelas otimizadas)
- ✅ **2.2:** Seed data (templates & settings padrão)
- ✅ **2.2:** uninstall.php (cleanup completo)
- ✅ **2.3:** Cache Manager (multi-tier caching)
- ✅ **2.3:** Form Processor básico (pipeline completo)
- ✅ **2.4:** PHPUnit test suite (26 tests, 100% passing)
- ✅ **2.4:** PSR-4 compliance refactoring
- ✅ **2.4:** Comprehensive test documentation
- ✅ **2.5:** CI/CD pipeline (GitHub Actions)
- ✅ **2.5:** PHPStan level 5 (static analysis)
- ✅ **2.5:** Integration test infrastructure

## 🧪 Testing & Quality

### Automated Testing
- ✅ **56 PHPUnit tests** - 100% passing, 251 assertions
- ✅ **Multi-PHP CI** - Tests on PHP 8.1, 8.2, 8.3
- ✅ **PHPStan Level 5** - Static analysis passing
- ✅ **Code Coverage** - Tracking enabled
- ✅ **GitHub Actions** - Automated testing on every push

### Test Suites
```bash
composer test                    # Run all tests
composer phpstan                 # Static analysis
composer phpcs                   # Coding standards
```

Ver documentação completa: [`tests/README.md`](tests/README.md)

## 🚀 Instalação (Dev)

### Requisitos
- WordPress 6.0+
- PHP 8.1+ (8.1, 8.2, 8.3 suportados)
- MySQL 5.7+ ou MariaDB 10.3+
- Composer
- Node.js 16+ & npm

### Setup

```bash
# Clone o repositório
git clone https://github.com/mrmsoares/Form-Flow-Pro.git
cd Form-Flow-Pro

# Install PHP dependencies
composer install

# Install Node dependencies
npm install

# Build assets
npm run build

# For development with watch mode
npm run dev
```

### Ativação
1. Copie a pasta do plugin para `wp-content/plugins/`
2. Ative o plugin no painel do WordPress
3. Acesse **FormFlow Pro** no menu admin

### Configuração Inicial

#### 1. Configurar API Autentique
```
WordPress Admin → FormFlow Pro → Settings → Autentique
- Insira sua API Key do Autentique
- Configure o email da empresa (signatário secundário)
```

#### 2. Configurar Formulário Elementor
1. Crie/edite um formulário no Elementor
2. Adicione a ação **FormFlow Pro Action**
3. Ative **Enable Digital Signature**
4. Salve o formulário

#### 3. Gerenciar Documentos
```
WordPress Admin → FormFlow Pro → Autentique
- Visualize todos os documentos
- Verifique status de assinaturas
- Reenvie links para signatários pendentes
- Acesse documentos diretamente no Autentique
```

## 📁 Estrutura do Projeto

```
formflow-pro-enterprise/
├── formflow-pro.php              # Main plugin file
├── composer.json                 # PHP dependencies
├── package.json                  # Node dependencies
├── webpack.config.js             # Build configuration
│
├── includes/                     # Core PHP code
│   ├── core/                     # Core modules
│   │   ├── class-cache-manager.php      # Multi-tier caching
│   │   └── class-form-processor.php     # Form processing pipeline
│   ├── Reporting/                # Advanced Reporting Module (V2.3.0)
│   │   ├── ReportGenerator.php          # PDF/Excel report generation
│   │   ├── D3Visualization.php          # D3.js chart system
│   │   └── ReportingManager.php         # Scheduling & history
│   ├── Automation/               # Visual Automation Builder (V3.0.0)
│   │   └── AutomationManager.php        # Workflow engine
│   ├── SSO/                      # Enterprise SSO (V3.0.0)
│   │   └── SSOManager.php               # OAuth 2.0 & SAML 2.0
│   ├── Marketplace/              # API Marketplace (V3.0.0)
│   │   └── MarketplaceManager.php       # Integrations & webhooks
│   ├── api/                      # API integrations
│   ├── admin/                    # Admin interface
│   │   ├── class-admin.php              # Admin controller
│   │   └── views/                       # Admin pages
│   ├── database/                 # Database layer
│   │   ├── class-database-manager.php   # Migration system
│   │   └── migrations/                  # Version migrations
│   └── ...
│
├── src/                          # Source files
│   ├── js/
│   │   ├── visualization.js             # D3.js chart rendering
│   │   ├── automation-builder.js        # Visual workflow builder
│   │   └── reporting.js                 # Report management UI
│   ├── css/
│   │   ├── visualization.css            # Chart styles
│   │   ├── automation-builder.css       # Builder styles
│   │   └── reporting.css                # Reporting UI styles
│   ├── admin/                           # Admin JavaScript
│   ├── elementor/                       # Elementor integration
│   └── scss/                            # SCSS styles
│
├── assets/                       # Compiled assets (gitignored)
│   └── ...
│
├── tests/                        # Test suites
│   ├── unit/
│   │   ├── Core/                        # Core module tests
│   │   ├── Database/                    # Database tests
│   │   ├── Reporting/                   # Reporting module tests
│   │   ├── Automation/                  # Automation tests
│   │   ├── SSO/                         # SSO module tests
│   │   └── Marketplace/                 # Marketplace tests
│   ├── integration/
│   └── mocks/
│
└── docs-planning/                # Planning documentation
    ├── 1.1-requirements/
    ├── 1.2-architecture/
    └── 1.3-database-performance/
```

## 🛠️ Desenvolvimento

### Build Commands

```bash
# Development build with watch
npm run dev

# Production build (minified)
npm run build

# Run tests
composer test

# Code standards check
composer phpcs

# Static analysis
composer phpstan
```

### Coding Standards
- **PHP:** PSR-12, WordPress Coding Standards
- **JavaScript:** ESLint
- **CSS:** Stylelint
- **Architecture:** PSR-4 autoloading

## 📚 Documentação

A documentação completa está em `docs-planning/`:

- **[PRD](docs-planning/1.1-requirements/PRD-FormFlowPro-Enterprise.md)** - Product Requirements
- **[User Research](docs-planning/1.1-requirements/User-Research-Report.md)** - Personas & Insights
- **[Competitive Analysis](docs-planning/1.1-requirements/Competitive-Analysis.md)** - Market Analysis
- **[Performance](docs-planning/1.1-requirements/Performance-Requirements.md)** - Performance Specs
- **[Architecture](docs-planning/1.2-architecture/Architecture-Overview.md)** - System Architecture
- **[Design System](docs-planning/1.2-architecture/Design-System.md)** - UI/UX Design
- **[Database](docs-planning/1.3-database-performance/Database-Schema.md)** - Database Schema

## 🎨 Design System

O plugin usa um design system completo com:
- **Design Tokens:** Cores, tipografia, espaçamentos
- **Grid System:** 12 colunas, mobile-first
- **Components:** 8 componentes principais
- **Accessibility:** WCAG 2.1 AA compliant
- **Dark Mode:** Suporte nativo

Ver: [`docs-planning/1.2-architecture/Design-System.md`](docs-planning/1.2-architecture/Design-System.md)

## 📊 Database Schema

10 tabelas otimizadas com:
- **15+ strategic indexes** para performance
- **Partitioning strategy** para 1M+ submissions
- **Query optimization** (450ms → 15ms)
- **Migration framework** para versionamento

Ver: [`docs-planning/1.3-database-performance/Database-Schema.md`](docs-planning/1.3-database-performance/Database-Schema.md)

## 🧪 Testes

```bash
# Run all tests
composer test

# Run with coverage
composer test:coverage

# Run specific test suite
vendor/bin/phpunit tests/unit/

# E2E tests (coming soon)
npm run test:e2e
```

## 🤝 Contribuindo

Este é um projeto em desenvolvimento ativo. Contribuições são bem-vindas!

1. Fork o projeto
2. Crie uma branch (`git checkout -b feature/AmazingFeature`)
3. Commit suas mudanças (`git commit -m 'Add some AmazingFeature'`)
4. Push para a branch (`git push origin feature/AmazingFeature`)
5. Abra um Pull Request

## 🎨 Funcionalidades Principais

### 📋 Gerenciamento de Formulários
- Interface admin completa (Dashboard, Forms, Submissions, Analytics, Autentique, Settings)
- Integração nativa com Elementor Pro
- Processamento assíncrono via Queue System
- Validação avançada de dados
- Sanitização e escape automático

### ✍️ Assinaturas Digitais (Autentique)
- **Criação de Documentos:** Geração automática de PDFs a partir de submissions
- **Multipart Upload:** Upload seguro via GraphQL conforme spec oficial
- **Múltiplos Signatários:** Suporte para signatário principal + empresa
- **Tracking Completo:** Tabela dedicada para documentos
- **Admin UI Intuitiva:**
  - Cards com estatísticas (Total, Pendentes, Assinados, Recusados)
  - DataTable com busca, filtros e paginação
  - Visualização de detalhes em modal
  - Reenvio de links de assinatura
  - Acesso direto ao Autentique
- **Webhooks:** Atualização automática de status via webhooks Autentique
- **Email Notifications:** Lembretes automáticos para assinaturas pendentes

### 📊 Queue System
- Processamento em background de tarefas pesadas
- Sistema de retry com tentativas configuráveis
- Priorização de jobs
- Cron jobs personalizados (5 minutos, semanal)
- Logs detalhados de execução

### 🗄️ Cache & Performance
- Multi-tier caching (Redis, Memcached, Transient, Database)
- TTL configurável
- Cleanup automático de cache expirado
- Otimização de queries (450ms → 15ms)

### 🌍 Internacionalização
- Tradução completa pt_BR (400+ strings)
- Estrutura pronta para novos idiomas
- Arquivos .pot, .po e .mo incluídos

### 📁 Logs & Archive
- Sistema de logs com níveis (Error, Warning, Info, Debug)
- Retention configurável (padrão: 30 dias)
- Archive automático de submissions antigas (90 dias)
- Cleanup via cron jobs

## 📝 Roadmap

### ✅ V2.1.0 (Current - PRODUCTION READY 100%)

**Phase 12: Settings UX & Performance**
- [x] Enhanced Autentique Settings Tab with status banner and quick stats
- [x] API key visibility toggle for security
- [x] Webhook configuration section with copy button
- [x] Document settings (auto-send, reminders, default message)
- [x] Getting started guide integrated
- [x] Option name consistency fix (formflow_autentique_api_key)
- [x] Settings.min.js properly loaded

**Queue System Optimizations**
- [x] Configurable batch size
- [x] Atomic job claiming (FOR UPDATE SKIP LOCKED)
- [x] Exponential backoff for retries
- [x] Dead letter queue for failed jobs
- [x] Auto-healing for stuck jobs
- [x] Queue statistics method (get_stats)

---

### ✅ V2.0.0 (Previous - Phase 10-11)
**Phase 1-2: Fundação**
- [x] Plugin skeleton & architecture
- [x] Admin interface (6 páginas: Dashboard, Forms, Submissions, Analytics, Autentique, Settings)
- [x] Database Manager & Migration system
- [x] Migration v2.0.0 (11 tabelas otimizadas)
- [x] Cache Manager (multi-tier)
- [x] Form Processor (pipeline completo)
- [x] PHPUnit test suite (56 tests, 251 assertions - 100% passing)
- [x] CI/CD pipeline (GitHub Actions - PHP 8.1, 8.2, 8.3)
- [x] PHPStan level 5 static analysis

**Phase 3-8: Core Features**
- [x] Integration com Elementor Pro
- [x] PDF generation
- [x] Email system
- [x] Queue system com custom cron schedules
- [x] Logs & Archive managers
- [x] Shortcodes system

**Phase 9: Critical Fixes**
- [x] Custom cron schedules (5 minutos, semanal)
- [x] Missing default options (5)
- [x] Queue schedule conflict resolution
- [x] Cache cleanup hook
- [x] Autentique integration connected
- [x] Duplicate code cleanup (-3,009 linhas)
- [x] All tests passing (26/26 - 100%)

**Phase 10: Autentique 100% + Admin UI + i18n**
- [x] Autentique 100% compliant (GraphQL multipart upload)
- [x] Admin UI completa para Autentique
- [x] Database table formflow_autentique_documents
- [x] AJAX handlers completos
- [x] Tradução pt_BR (400+ strings)
- [x] README atualizado

**Phase 11: Polimento Final**
- [x] Cache statistics (hit rate calculation) implementado
- [x] PHP 8.0 dropped (requires 8.1+)
- [x] CI/CD fixes para coverage
- [x] Testes de hit rate funcionando

### ✅ V3.0.0 (Current - Enterprise Release)

**Advanced Reporting Module (V2.3.0)**
- [x] D3.js v7 visualization library with 12 chart types
- [x] Report Generator with PDF, Excel, CSV, JSON, HTML exports
- [x] Scheduled Reports (daily, weekly, monthly, quarterly)
- [x] KPI Dashboard widgets with trend indicators
- [x] Report history with automatic cleanup

**Visual Automation Builder (V3.0.0)**
- [x] Drag-and-drop workflow builder
- [x] 13 node types (triggers, actions, logic)
- [x] Visual connections with bezier curves
- [x] Property panel for node configuration
- [x] Keyboard shortcuts and context menu
- [x] Execution history and logs

**Enterprise SSO Manager (V3.0.0)**
- [x] OAuth 2.0 (Google, Microsoft, Okta, Auth0)
- [x] SAML 2.0 support
- [x] User provisioning and linking
- [x] Token management and validation

**API Marketplace (V3.0.0)**
- [x] Pre-built integrations (Slack, Salesforce, HubSpot, Zapier)
- [x] Custom integration registration
- [x] Webhook system per event/form
- [x] API credential management

**Testing & Quality**
- [x] 100+ PHPUnit tests across all modules
- [x] Tests for Reporting, Automation, SSO, Marketplace
- [x] Frontend assets (JS + CSS) for all modules

---

### 🎯 V3.1.0 (Future)
- [ ] AI-powered features (auto-fill, smart validation)
- [ ] Mobile app companion
- [ ] Multi-site network support
- [ ] White-label capabilities enhanced

### 🌟 V4.0.0 (Future)
- [ ] GraphQL API
- [ ] Real-time collaboration
- [ ] Advanced permissions system
- [ ] Custom field builder

## 📄 Licença

GPL-2.0+ - Ver arquivo [LICENSE](LICENSE) para detalhes.

## 👥 Equipe

- **Product Owner:** [TBD]
- **Tech Lead:** [TBD]
- **Developers:** FormFlow Pro Team

## 📞 Suporte

- **Documentação:** [docs.formflowpro.com](https://docs.formflowpro.com)
- **Issues:** [GitHub Issues](https://github.com/mrmsoares/Form-Flow-Pro/issues)
- **Email:** dev@formflowpro.com

---

**Made with ❤️ by FormFlow Pro Team**
