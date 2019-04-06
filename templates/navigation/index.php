<ul>
    <li id="navigation-favorites" class="collapsible">
        <a class="icon-favorite" href="#"><?php p($l->t('Favorites')); ?></a>
        <div class="app-navigation-entry-utils">
            <ul>
                <li id="addFavoriteButton" class="app-navigation-entry-utils-menu-button" title="<?php p($l->t('Add a favorite')); ?>">
                    <button class="icon-add"></button>
                </li>
                <li id="toggleFavoritesButton" class="app-navigation-entry-utils-menu-button" title="<?php p($l->t('Toggle favorites')); ?>">
                    <button class="icon-toggle"></button>
                </li>
                <li class="app-navigation-entry-utils-menu-button favoritesMenuButton">
                    <button></button>
                </li>
            </ul>
        </div>
        <div class="app-navigation-entry-menu">
            <ul>
                <li>
                    <a href="#" id="export-all-favorites">
                        <span class="icon-category-office"></span>
                        <span><?php p($l->t('Export all favorites')); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#" id="export-displayed-favorites">
                        <span class="icon-category-office"></span>
                        <span><?php p($l->t('Export displayed favorites')); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#" id="import-favorites">
                        <span class="icon-folder"></span>
                        <span><?php p($l->t('Import favorites from gpx files')); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#" id="select-all-categories">
                        <span class="icon-category-enabled"></span>
                        <span><?php p($l->t('Show all categories')); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#" id="select-no-categories">
                        <span class="icon-category-disabled"></span>
                        <span><?php p($l->t('Hide all categories')); ?></span>
                    </a>
                </li>
            </ul>
        </div>
        <ul id="category-list">
        </ul>
    </li>
    <li id="navigation-routing">
        <a class="" href="#"><?php p($l->t('Routing')); ?></a>
        <div class="app-navigation-entry-utils">
            <ul>
                <li id="toggleRoutingButton" class="app-navigation-entry-utils-menu-button" title="<?php p($l->t('Toggle routing')); ?>">
                    <button class="icon-toggle"></button>
                </li>
                <li class="app-navigation-entry-utils-menu-button routingMenuButton">
                    <button></button>
                </li>
            </ul>
        </div>
        <div class="app-navigation-entry-menu">
            <ul>
                <li>
                    <a href="#" class="exportCurrentRoute">
                        <span class="icon-category-office"></span>
                        <span><?php p($l->t('Export current route to gpx')); ?></span>
                    </a>
                </li>
            </ul>
        </div>
    </li>
    <img id="dummylogo"/>
    <li id="navigation-photos">
        <a class="icon-picture" href="#"><?php p($l->t('Photos')); ?></a>
        <div class="app-navigation-entry-utils">
            <ul>
                <li class="app-navigation-entry-utils-counter">
                    <span></span>
                </li>
                <li id="togglePhotosButton" class="app-navigation-entry-utils-menu-button" title="<?php p($l->t('Toggle photos')); ?>">
                    <button class="icon-toggle"></button>
                </li>
                <li class="app-navigation-entry-utils-menu-button photosMenuButton">
                    <button></button>
                </li>
            </ul>
        </div>
        <div class="app-navigation-entry-menu">
            <ul>
                <li>
                    <a href="#" class="dummyoption">
                        <span class="icon-category-office"></span>
                        <span><?php p($l->t('Nothing yet')); ?></span>
                    </a>
                </li>
            </ul>
        </div>
    </li>
    <li id="navigation-contacts">
        <a class="icon-group" href="#"><?php p($l->t('Contacts')); ?></a>
        <div class="app-navigation-entry-utils">
            <ul>
                <li class="app-navigation-entry-utils-counter">
                    <span></span>
                </li>
                <li id="toggleContactsButton" class="app-navigation-entry-utils-menu-button" title="<?php p($l->t('Toggle contacts')); ?>">
                    <button class="icon-toggle"></button>
                </li>
                <li class="app-navigation-entry-utils-menu-button contactsMenuButton">
                    <button></button>
                </li>
            </ul>
        </div>
        <div class="app-navigation-entry-menu">
            <ul>
                <li>
                    <a href="#" class="dummyoption">
                        <span class="icon-category-office"></span>
                        <span><?php p($l->t('Nothing yet')); ?></span>
                    </a>
                </li>
            </ul>
        </div>
    </li>
    <li id="navigation-tracks" class="collapsible">
        <a class="" href="#"><?php p($l->t('Tracks')); ?></a>
        <div class="app-navigation-entry-utils">
            <ul>
                <li id="addTrackButton" class="app-navigation-entry-utils-menu-button" title="<?php p($l->t('Load a track file')); ?>">
                    <button class="icon-add"></button>
                </li>
                <li id="toggleTracksButton" class="app-navigation-entry-utils-menu-button" title="<?php p($l->t('Toggle tracks')); ?>">
                    <button class="icon-toggle"></button>
                </li>
                <li class="app-navigation-entry-utils-menu-button tracksMenuButton">
                    <button></button>
                </li>
            </ul>
        </div>
        <div class="app-navigation-entry-menu">
            <ul>
                <li>
                    <a href="#" id="select-all-trackss">
                        <span class="icon-category-enabled"></span>
                        <span><?php p($l->t('Show all tracks')); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#" id="select-no-tracks">
                        <span class="icon-category-disabled"></span>
                        <span><?php p($l->t('Hide all tracks')); ?></span>
                    </a>
                </li>
            </ul>
        </div>
        <ul id="category-list">
        </ul>
    </li>
</ul>
