<ul>
    <li id="navigation-photos"><a class="icon-link" href="#"><?php p($l->t('Photos')); ?></a></li>
    <li id="navigation-favorites" class="collapsible">
        <a class="icon-favorite" href="#"><?php p($l->t('Favorites')); ?></a>
        <div class="app-navigation-entry-utils">
            <ul>
                <li class="app-navigation-entry-utils-counter">
                    <span></span>
                </li>
                <li class="app-navigation-entry-utils-menu-button favoritesMenuButton">
                    <button></button>
                </li>
            </ul>
        </div>
        <div class="app-navigation-entry-menu">
            <ul>
                <li>
                    <a href="#" class="addFavorite">
                        <span class="icon-add"></span>
                        <span><?php p($l->t('Add a favorite')); ?></span>
                    </a>
                </li>
                <li>
                    <a href="#" class="exportFavorites">
                        <span class="icon-category-office"></span>
                        <span><?php p($l->t('Export to gpx')); ?></span>
                    </a>
                </li>
            </ul>
        </div>
        <ul id="category-list">
        </ul>
    </li>
    <!--li>
        <a href="#">First level container</a>
        <ul>
            <li><a href="#">Second level entry</a></li>
            <li><a href="#">Second level entry</a></li>
        </ul>
    </li-->
    <img id="dummylogo"/>
</ul>
