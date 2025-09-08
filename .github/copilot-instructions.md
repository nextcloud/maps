# Nextcloud Maps App

Nextcloud Maps is a full-featured mapping application built as a Nextcloud app. It combines a PHP backend with a Vue.js frontend and requires both development environments to work effectively.

Always reference these instructions first and fallback to search or bash commands only when you encounter unexpected information that does not match the info here.

## Working Effectively

### Bootstrap and Build
- **CRITICAL**: All builds take significant time. NEVER CANCEL any build commands.
- **Dependencies**: Ensure you have Node.js (≥22.0.0 preferred, 20+ works with warnings), npm (≥10.5.0), PHP (8.1-8.4), Composer, and make installed
- **Full build from scratch**: `make distclean && make` -- takes 2-3 minutes total. NEVER CANCEL. Set timeout to 10+ minutes.
- **Initial build**: `make` -- takes 4-5 minutes on first run. NEVER CANCEL. Set timeout to 10+ minutes.
- **Development build**: `make dev` -- takes 30 seconds after initial build. NEVER CANCEL. Set timeout to 5+ minutes.
- **JavaScript only**: 
  - `npm ci` -- takes 45-80 seconds. NEVER CANCEL. Set timeout to 5+ minutes.
  - `npm run build` -- takes 60-70 seconds. NEVER CANCEL. Set timeout to 5+ minutes.
  - `npm run dev` -- development build, faster than production
- **PHP only**: `composer install --prefer-dist` -- Usually works but may fail with GitHub auth issues; will retry from source automatically

### Linting and Code Quality
- **JavaScript linting**: `npm run lint` -- takes 10 seconds (shows ~2500 issues, expected)
- **JavaScript lint fix**: `npm run lint:fix` -- takes 11 seconds, fixes some issues automatically
- **CSS linting**: `npm run stylelint` -- takes 3 seconds (shows ~230 issues, expected)
- **CSS lint fix**: `npm run stylelint:fix`
- **PHP linting**: `composer run lint` -- takes 4 seconds, should pass
- **PHP code style check**: `composer run cs:check` -- takes 4 seconds, should pass
- **PHP code style fix**: `composer run cs:fix`
- **Static analysis**: `composer run psalm` -- may fail due to PHP version requirements in some environments
- **VALIDATION REQUIREMENT**: Always run `npm run lint` and `composer run cs:check` before committing changes or the CI will fail.
- **NOTE**: Many linting issues are expected in this codebase and don't need to be fixed for basic functionality

### Testing
- **CRITICAL LIMITATION**: PHP tests require a full Nextcloud server installation and cannot be run standalone
- **PHP unit tests**: `composer run test:unit` -- WILL FAIL without Nextcloud environment
- **PHP integration tests**: `composer run test:integration` -- WILL FAIL without Nextcloud environment
- **Frontend unit tests**: Currently disabled (no npm test command configured)
- **Manual testing**: This is a web application that must be tested within a Nextcloud instance

### Clean Commands
- `make clean` -- removes build artifacts
- `make distclean` -- removes all dependencies and build artifacts
- Rebuilding from scratch: `make distclean && make` -- takes 5+ minutes total. NEVER CANCEL.

## Project Structure

### Frontend (Vue.js)
- **Main entry point**: `src/main.js`
- **Components**: `src/components/` - Vue.js components including Map, Sidebar, Navigation items
- **Views**: `src/views/` - Main app views (App.vue, TrackMetadataTab.vue)
- **Services**: `src/services/` - API communication layer
- **Store**: `src/store/` - Vuex state management
- **Utils**: `src/utils/` - Utility functions and common code
- **Build output**: `js/` - Generated JavaScript bundles

### Backend (PHP)
- **Controllers**: `lib/Controller/` - API endpoints and page controllers
- **Services**: `lib/Service/` - Business logic layer  
- **Database**: `lib/DB/` - Database entities and mappers
- **Commands**: `lib/Command/` - CLI commands (occ integration)
- **Settings**: `lib/Settings/` - Admin settings
- **Migration**: `lib/Migration/` - Database migrations
- **App info**: `appinfo/info.xml` - App metadata and dependencies

### Configuration
- **Webpack**: `webpack.js` - JavaScript build configuration
- **Babel**: `babel.config.js` - JavaScript transpilation
- **ESLint**: `.eslintrc.js` - JavaScript linting rules
- **Stylelint**: `stylelint.config.js` - CSS linting rules  
- **PHP CS Fixer**: `.php-cs-fixer.dist.php` - PHP code style rules
- **Psalm**: `psalm.xml` - PHP static analysis configuration

## Key Workflows

### Making Changes
1. **Setup**: Run `make` initially (5+ min timeout)
2. **Development**: Use `make dev` for faster incremental builds
3. **Linting**: Always run `npm run lint` and `composer run cs:check`
4. **Manual testing**: Test changes in a real Nextcloud environment

### Common File Locations
- **Main map component**: `src/components/Map.vue` 
- **App entry point**: `src/views/App.vue`
- **API routes**: `appinfo/routes.php`
- **Main controller**: `lib/Controller/PageController.php`
- **Package config**: `package.json` (frontend deps), `composer.json` (PHP deps)

### Build Artifacts to Ignore
- `js/` - Generated JavaScript files
- `node_modules/` - npm dependencies  
- `vendor/` - Composer dependencies
- `build/` - Build artifacts directory

### Nextcloud App Context
- **Installation**: This app must be placed in `apps/maps/` within a Nextcloud installation
- **Dependencies**: Requires Nextcloud 30-31, PHP 8.1-8.4, exif extension
- **CLI commands**: `./occ maps:scan-photos`, `./occ maps:scan-tracks`
- **Settings**: Admin settings available in Nextcloud admin panel

## Validation Scenarios
- **Build validation**: After changes, run `make dev` to ensure the build succeeds
- **Lint validation**: Run both `npm run lint` and `composer run cs:check` 
- **Manual testing**: The app requires actual user interaction testing within Nextcloud:
  - View maps with different tile layers
  - Add/edit/delete favorites  
  - Upload and view GPS tracks
  - View photos with GPS data on map
  - Test sharing functionality
- **CI compatibility**: The GitHub workflows will run Node.js build, PHP linting, and integration tests within Nextcloud environment

## Development Notes
- **Node.js version**: Package.json requires Node 22+, but Node 20+ works with warnings
- **Asset size warnings**: Large JavaScript bundles (8MB+ main.js) are expected for this mapping application
- **GitHub token issues**: Composer may request GitHub tokens; this can be skipped in many cases
- **Psalm version conflicts**: Static analysis may fail due to PHP version mismatches
- **TODO/FIXME items**: The codebase contains various TODOs, particularly around jQuery migration and authentication