<ul>
    <li id="navigation-favorites" class="collapsible">
        <a class="icon-favorite" href="#"><?php p($l->t('Your favorites')); ?></a>
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
                    <a href="#" id="export-displayed-favorites">
                        <span class="icon-category-office"></span>
                        <span><?php p($l->t('Export')); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#" id="import-favorites">
                        <span class="icon-folder"></span>
                        <span><?php p($l->t('Import from gpx/kml/kmz')); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#" id="toggle-all-categories">
                        <span class="icon-category-enabled"></span>
                        <span><?php p($l->t('Toggle all')); ?></span>
                    </a>
                </li>
            </ul>
        </div>
        <ul id="category-list">
        </ul>
    </li>
    <li id="navigation-photos">
        <a class="icon-picture" href="#"><?php p($l->t('Your photos')); ?></a>
        <div class="app-navigation-entry-utils">
            <ul>
                <li class="app-navigation-entry-utils-counter">
                    <span></span>
                </li>
            </ul>
        </div>
        <!--ul>
            <li id="navigation-nonLocalizedPhotos">
                <a class="icon-picture" href="#"><?php //p($l->t('without geo tag')); ?></a>
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
                                <span><?php //p($l->t('Save all visibile')); ?></span>
                            </a>
                        </li>
                    </ul>
                </div>
            </li>
        </ul-->
    </li>
    <li id="navigation-contacts" class="collapsible">
        <a class="icon-group" href="#"><?php p($l->t('Your contacts')); ?></a>
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
                    <a href="#" id="toggle-all-contact-groups">
                        <span class="icon-category-enabled"></span>
                        <span><?php p($l->t('Toggle all')); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#" id="zoom-all-contact-groups">
                        <span class="icon-search"></span>
                        <span><?php p($l->t('Zoom to bounds')); ?></span>
                    </a>
                </li>
            </ul>
        </div>
        <ul id="contact-group-list">
        </ul>
    </li>
    <li id="navigation-devices" class="collapsible">
        <a class="icon-phone" href="#"><?php p($l->t('Your devices')); ?></a>
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
                        <span><?php p($l->t('Refresh positions')); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#" id="select-all-devices">
                        <span class="icon-category-enabled"></span>
                        <span><?php p($l->t('Show all')); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#" id="select-no-devices">
                        <span class="icon-category-disabled"></span>
                        <span><?php p($l->t('Hide all')); ?></span>
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
                        <span><?php p($l->t('Delete all')); ?></span>
                    </a>
                </li>
            </ul>
        </div>
        <ul id="device-list">
        </ul>
    </li>
    <li id="navigation-tracks" class="collapsible">
        <a class="icon-category-monitoring" href="#"><?php p($l->t('Your tracks')); ?></a>
        <div class="app-navigation-entry-utils">
            <ul>
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
                        <span><?php p($l->t('Show all')); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#" id="select-no-tracks">
                        <span class="icon-category-disabled"></span>
                        <span><?php p($l->t('Hide all')); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#" id="sort-name-tracks">
                        <span class="icon-tag"></span>
                        <span><?php p($l->t('Sort by name')); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#" id="sort-date-tracks">
                        <span class="icon-calendar-dark"></span>
                        <span><?php p($l->t('Sort by date')); ?></span>
                    </a>
                </li>
            </ul>
        </div>
        <ul id="track-list">
        </ul>
    </li>
</ul>
<input id="trackcolorinput" type="color"></input>
<input id="devicecolorinput" type="color"></input>
