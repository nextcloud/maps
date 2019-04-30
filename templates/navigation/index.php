<ul>
    <li id="navigation-favorites" class="collapsible">
        <a class="icon-favorite" href="#"><?php p($l->t('Favorites')); ?></a>
        <div class="app-navigation-entry-utils">
            <ul>
                <li id="addFavoriteButton" class="app-navigation-entry-utils-menu-button" title="<?php p($l->t('Add a favorite')); ?>">
                    <button class="icon-add"></button>
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
                        <span><?php p($l->t('Import favorites from gpx/kml/kmz files')); ?></span>
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
    <li id="navigation-photos" class="collapsible">
        <a class="icon-picture" href="#"><?php p($l->t('Photos')); ?></a>
        <div class="app-navigation-entry-utils">
            <ul>
                <li class="app-navigation-entry-utils-counter">
                    <span></span>
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
        <ul>
            <li id="navigation-nonLocalizedPhotos">
                <a class="icon-picture" href="#"><?php p($l->t('without geo tag')); ?></a>
                <div class="app-navigation-entry-utils">
                    <ul>
                        <li class="app-navigation-entry-utils-counter">
                            <span></span>
                        </li>
                        <li class="app-navigation-entry-utils-menu-button nonLocalizedPhotosMenuButton">
                            <button></button>
                        </li>
                    </ul>
                </div>
                <div class="app-navigation-entry-menu">
                    <ul>
                        <li>
                            <a href="#" class="save-all-nonlocalized">
                                <span class="icon-category-office"></span>
                                <span><?php p($l->t('Save all visibile')); ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
        </ul>
    </li>
    <li id="navigation-contacts">
        <a class="icon-group" href="#"><?php p($l->t('Contacts')); ?></a>
        <div class="app-navigation-entry-utils">
            <ul>
                <li class="app-navigation-entry-utils-counter">
                    <span></span>
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
                <li class="app-navigation-entry-utils-menu-button tracksMenuButton">
                    <button></button>
                </li>
            </ul>
        </div>
        <div class="app-navigation-entry-menu">
            <ul>
                <li>
                    <a href="#" id="select-all-tracks">
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
                <li>
                    <a href="#" id="add-track-folder">
                        <span class="icon-folder"></span>
                        <span><?php p($l->t('Load a directory')); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#" id="remove-all-tracks">
                        <span class="icon-delete"></span>
                        <span><?php p($l->t('Remove all tracks')); ?></span>
                    </a>
                </li>
            </ul>
        </div>
        <ul id="track-list">
        </ul>
    </li>
    <li id="navigation-devices" class="collapsible">
        <a class="icon-phone" href="#"><?php p($l->t('Devices')); ?></a>
        <div class="app-navigation-entry-utils">
            <ul>
                <li class="app-navigation-entry-utils-menu-button devicesMenuButton">
                    <button></button>
                </li>
            </ul>
        </div>
        <div class="app-navigation-entry-menu">
            <ul>
                <li>
                    <a href="#" id="refresh-all-devices">
                        <span class="icon-download"></span>
                        <span><?php p($l->t('Refresh devices positions')); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#" id="select-all-devices">
                        <span class="icon-category-enabled"></span>
                        <span><?php p($l->t('Show all devices')); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#" id="select-no-devices">
                        <span class="icon-category-disabled"></span>
                        <span><?php p($l->t('Hide all devices')); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#" id="export-all-devices">
                        <span class="icon-category-office"></span>
                        <span><?php p($l->t('Export all')); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#" id="import-devices">
                        <span class="icon-folder"></span>
                        <span><?php p($l->t('Import devices')); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#" id="delete-all-devices">
                        <span class="icon-delete"></span>
                        <span><?php p($l->t('Delete all devices history')); ?></span>
                    </a>
                </li>
            </ul>
        </div>
        <ul id="device-list">
        </ul>
    </li>
</ul>
<input id="trackcolorinput" type="color"></input>
<input id="devicecolorinput" type="color"></input>
