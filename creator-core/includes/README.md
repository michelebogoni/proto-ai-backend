# Creator Core Plugin - Includes

This directory contains the core PHP classes for the Creator AI WordPress plugin. The plugin provides an AI-powered assistant that can modify WordPress sites through a chat interface.

## Architecture Overview

```
includes/
├── Admin/           # WordPress admin UI (dashboard, settings)
├── API/             # REST API endpoints and controllers
├── Audit/           # Operation tracking and logging
├── Backup/          # Snapshot and rollback system
├── Chat/            # Chat interface and message handling
├── Context/         # Site context collection for AI
├── Development/     # Code analysis and plugin generation
├── Executor/        # Action execution engine
├── Integrations/    # Third-party plugin integrations
├── Permission/      # Capability and role management
├── User/            # User profile and preferences
├── Activator.php    # Plugin activation hooks
├── Deactivator.php  # Plugin deactivation hooks
├── Loader.php       # Hook registration system
└── Autoloader.php   # PSR-4 autoloader
```

## Macro Areas

### API (`API/`)

REST API layer that handles requests from the chat interface and external tools.

| File | Purpose |
|------|---------|
| `REST_API.php` | Main API router and authentication |
| `RateLimiter.php` | Request rate limiting |
| `Controllers/` | Individual endpoint controllers |

**Controllers:**
- `ChatController.php` - AI chat message handling
- `ActionController.php` - Execute AI-generated actions
- `ContextController.php` - Site context retrieval
- `ElementorController.php` - Elementor-specific operations
- `FileController.php` - File system operations
- `DatabaseController.php` - Database queries
- `PluginController.php` - Plugin management
- `AnalyzeController.php` - Code analysis
- `SystemController.php` - System information

### Integrations (`Integrations/`)

Adapters for popular WordPress plugins and services.

| File | Purpose |
|------|---------|
| `ProxyClient.php` | Communication with Creator AI Proxy |
| `ElementorIntegration.php` | Elementor page builder |
| `ElementorPageBuilder.php` | Build Elementor pages programmatically |
| `ElementorActionHandler.php` | Handle Elementor widget actions |
| `ElementorSchemaLearner.php` | Learn Elementor widget schemas |
| `WooCommerceIntegration.php` | WooCommerce product management |
| `ACFIntegration.php` | Advanced Custom Fields |
| `RankMathIntegration.php` | RankMath SEO |
| `WPCodeIntegration.php` | WPCode snippets |
| `LiteSpeedIntegration.php` | LiteSpeed cache |
| `PluginDetector.php` | Detect installed plugins |

### Executor (`Executor/`)

Engine that executes AI-generated actions on the WordPress site.

| File | Purpose |
|------|---------|
| `CodeExecutor.php` | Main action dispatcher |
| `OperationFactory.php` | Create operation instances |
| `ExecutionVerifier.php` | Verify action success |
| `ErrorHandler.php` | Handle execution errors |
| `CustomCodeLoader.php` | Load user custom code |
| `CustomFileManager.php` | Manage custom PHP files |

**Supported Actions:**
- Create/update/delete posts and pages
- Manage Elementor content
- Create custom plugins
- Execute database queries
- Modify WordPress options
- File system operations

### Context (`Context/`)

Collects and manages site context for AI prompts.

| File | Purpose |
|------|---------|
| `CreatorContext.php` | Main context manager |
| `ContextLoader.php` | Load context from various sources |
| `ContextCache.php` | Cache context for performance |
| `ContextRefresher.php` | Refresh stale context |
| `SystemPrompts.php` | AI system prompt templates |
| `ThinkingLogger.php` | Log AI reasoning |
| `PluginDocsRepository.php` | Plugin documentation cache |

### Backup (`Backup/`)

Snapshot and rollback system for safe AI operations.

| File | Purpose |
|------|---------|
| `SnapshotManager.php` | Create and manage snapshots |
| `DeltaBackup.php` | Incremental backup support |
| `Rollback.php` | Restore from snapshots |

### Chat (`Chat/`)

Chat interface and message processing.

| File | Purpose |
|------|---------|
| `ChatInterface.php` | Chat UI rendering |
| `MessageHandler.php` | Process user messages |
| `PhaseDetector.php` | Detect conversation phase |
| `ContextCollector.php` | Collect context for messages |

### Admin (`Admin/`)

WordPress admin interface components.

| File | Purpose |
|------|---------|
| `Dashboard.php` | Main plugin dashboard |
| `Settings.php` | Plugin settings page |
| `SetupWizard.php` | Initial setup wizard |

### Development (`Development/`)

Tools for code analysis and plugin generation.

| File | Purpose |
|------|---------|
| `CodeAnalyzer.php` | Analyze PHP/JS code |
| `PluginGenerator.php` | Generate custom plugins |
| `FileSystemManager.php` | File operations |
| `DatabaseManager.php` | Database operations |

### Audit (`Audit/`)

Operation tracking and logging.

| File | Purpose |
|------|---------|
| `AuditLogger.php` | Log all operations |
| `OperationTracker.php` | Track operation history |

### Permission (`Permission/`)

Role and capability management.

| File | Purpose |
|------|---------|
| `CapabilityChecker.php` | Check user capabilities |
| `RoleMapper.php` | Map roles to permissions |

## Data Flow

```
User Message (Chat UI)
        │
        ▼
   ChatController ──────► ProxyClient ──────► Creator AI Proxy
        │                                            │
        │                                            ▼
        │                                    AI Model (Claude/Gemini)
        │                                            │
        │                     ◄──────────────────────┘
        ▼                         (AI Response with Actions)
   ActionController
        │
        ▼
   CodeExecutor ──────► OperationFactory
        │                      │
        │                      ▼
        │               Specific Handler
        │               (Elementor, WooCommerce, etc.)
        │                      │
        ▼                      ▼
   ExecutionVerifier     WordPress APIs
        │
        ▼
   AuditLogger
```

## Key Classes

### `ProxyClient`
Handles all communication with the Creator AI Proxy API, including:
- License validation
- AI request routing
- Token usage tracking

### `CodeExecutor`
Central dispatcher for executing AI-generated actions:
- Creates backups before modifications
- Routes actions to appropriate handlers
- Verifies execution success
- Logs all operations

### `CreatorContext`
Builds comprehensive site context for AI prompts:
- Active theme info
- Installed plugins
- Custom post types
- Elementor templates
- WooCommerce products
