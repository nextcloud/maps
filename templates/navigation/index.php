<ul>
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
                        <span><?php p($l->t('Export route to gpx')); ?></span>
                    </a>
                </li>
            </ul>
        </div>
    </li>
    <img id="dummylogo"/>
</ul>
