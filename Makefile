# This file is licensed under the Affero General Public License version 3 or
# later. See the COPYING file.
# @author Bernhard Posselt <dev@bernhard-posselt.com>
# @copyright Bernhard Posselt 2016

# Generic Makefile for building and packaging a Nextcloud app which uses npm and
# Composer.
#
# Dependencies:
# * make
# * which
# * curl: used if phpunit and composer are not installed to fetch them from the web
# * tar: for building the archive
# * npm: for building and testing everything JS
#
# If no composer.json is in the app root directory, the Composer step
# will be skipped. The same goes for the package.json which can be located in
# the app root or the js/ directory.
#
# The npm command by launches the npm build script:
#
#    npm run build
#
# The npm test command launches the npm test script:
#
#    npm run test
#
# The idea behind this is to be completely testing and build tool agnostic. All
# build tools and additional package managers should be installed locally in
# your project, since this won't pollute people's global namespace.
#
# The following npm scripts in your package.json install and update the bower
# and npm dependencies and use gulp as build system (notice how everything is
# run from the node_modules folder):
#
#    "scripts": {
#        "test": "node node_modules/gulp-cli/bin/gulp.js karma",
#        "prebuild": "npm install && node_modules/bower/bin/bower install && node_modules/bower/bin/bower update",
#        "build": "node node_modules/gulp-cli/bin/gulp.js"
#    },

app_name=$(notdir $(CURDIR))
build_tools_directory=$(CURDIR)/build/tools
source_build_directory=$(CURDIR)/build/artifacts/source
source_package_name=$(source_build_directory)/$(app_name)
appstore_build_directory=$(CURDIR)/build/artifacts/appstore
appstore_package_name=$(appstore_build_directory)/$(app_name)
npm=$(shell which npm 2> /dev/null)
composer=$(shell which composer 2> /dev/null)

cert_dir=$(HOME)/.nextcloud/certificates
webserveruser ?= wwwrun
occ_dir ?= /srv/www/htdocs/server
app_version=$(version)

all: build

# Fetches the PHP and JS dependencies and compiles the JS. If no composer.json
# is present, the composer step is skipped, if no package.json or js/package.json
# is present, the npm step is skipped
.PHONY: build
build:
ifneq (,$(wildcard $(CURDIR)/composer.json))
	make composer
endif
ifneq (,$(wildcard $(CURDIR)/package.json))
	make npm
endif

# Installs and updates the composer dependencies. If composer is not installed
# a copy is fetched from the web
.PHONY: composer
composer:
ifeq (, $(composer))
	@echo "No composer command available, downloading a copy from the web"
	mkdir -p $(build_tools_directory)
	curl -sS https://getcomposer.org/installer | php
	mv composer.phar $(build_tools_directory)
	php $(build_tools_directory)/composer.phar install --prefer-dist
	php $(build_tools_directory)/composer.phar update --prefer-dist
else
	composer install --prefer-dist
	composer update --prefer-dist
endif

# Installs npm dependencies
.PHONY: npm
npm:
	$(npm) install
	sed -i.bak 's/L\.Browser\.touch/L.Browser.mobile/g' node_modules/leaflet.elevation/dist/Leaflet.Elevation-0.0.2.min.js && rm node_modules/leaflet.elevation/dist/Leaflet.Elevation-0.0.2.min.js.bak
	
# Removes the appstore build
.PHONY: clean
clean:
	rm -rf ./build

# Same as clean but also removes dependencies installed by composer, bower and
# npm
.PHONY: distclean
distclean: clean
	rm -rf vendor
	rm -rf node_modules
	rm -rf js/vendor
	rm -rf js/node_modules

# Builds the source and appstore package
.PHONY: dist
dist:
	make source
	make appstore

# Builds the source package
.PHONY: source
source:
	rm -rf $(source_build_directory)
	mkdir -p $(source_build_directory)
	rsync -a \
	--exclude=.git \
	--exclude=build \
	--exclude=js/node_modules \
	--exclude=node_modules \
	--exclude=*.log \
	--exclude=js/*.log \
	../$(app_name) $(source_build_directory)
	tar -czf $(source_build_directory)/$(app_name)-$(app_version).tar.gz \
		-C $(source_build_directory) $(app_name)

# Builds the source package for the app store, ignores php and js tests
.PHONY: appstore
appstore:
	rm -rf $(appstore_build_directory)
	mkdir -p $(appstore_build_directory)
	rsync -a \
	--exclude=.git \
	--exclude=build \
	--exclude=tests \
	--exclude=Makefile \
	--exclude=*.log \
	--exclude=phpunit*xml \
	--exclude=composer.* \
	--exclude=js/node_modules \
	--exclude=js/tests \
	--exclude=js/test \
	--exclude=js/*.log \
	--exclude=js/package.json \
	--exclude=js/bower.json \
	--exclude=js/karma.* \
	--exclude=js/protractor.* \
	--exclude=package.json \
	--exclude=bower.json \
	--exclude=karma.* \
	--exclude=protractor\.* \
	--exclude=translationfiles \
	--exclude=.* \
	--exclude=js/.* \
	../$(app_name) $(appstore_build_directory)
	# give the webserver user the right to create signature file
	sudo chown $(webserveruser) $(appstore_package_name)/appinfo
	sudo -u $(webserveruser) php $(occ_dir)/occ integrity:sign-app --privateKey=$(cert_dir)/$(app_name).key --certificate=$(cert_dir)/$(app_name).crt --path=$(appstore_package_name)/
	sudo chown -R $(USER) $(appstore_package_name)/appinfo
	tar -czf $(appstore_package_name)-$(app_version).tar.gz \
		-C $(appstore_build_directory) $(app_name)
	echo NEXTCLOUD------------------------------------------
	openssl dgst -sha512 -sign $(cert_dir)/$(app_name).key $(appstore_package_name)-$(app_version).tar.gz | openssl base64

# Command for running JS and PHP tests. Works for package.json files in the js/
# and root directory. If phpunit is not installed systemwide, a copy is fetched
# from the internet
.PHONY: test
test:
	#$(npm) run test
ifeq (, $(shell which phpunit 2> /dev/null))
	@echo "No phpunit command available, downloading a copy from the web"
	mkdir -p $(build_tools_directory)
	curl -sSL https://phar.phpunit.de/phpunit.phar -o $(build_tools_directory)/phpunit.phar
	php $(build_tools_directory)/phpunit.phar -c phpunit.xml
	php $(build_tools_directory)/phpunit.phar -c phpunit.integration.xml
else
	phpunit -c phpunit.xml --coverage-clover build/php-unit.clover
	phpunit -c phpunit.integration.xml --coverage-clover build/php-unit.clover
endif
