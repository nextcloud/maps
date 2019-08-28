<div id="app-settings">
    <div id="app-settings-header">
        <button class="settings-button" data-apps-slide-toggle="#app-settings-content">
            <?php p($l->t('Settings')); ?>
        </button>
    </div>
    <div id="app-settings-content">
        <!-- Your settings in here -->
        <input type="checkbox" id="track-me" class="checkbox">
        <label for="track-me"><?php p($l->t('Track my position')); ?></label>
        <input type="checkbox" id="display-slider" class="checkbox">
        <label for="display-slider"><?php p($l->t('Display time filter slider')); ?></label>
        <p>
            <?php p($l->t('Keep in mind that map projections always distort sizes of countries. The standard Mercator projection is particularly biased. Read more at:')); ?><br>
            <a href="http://kai.sub.blue/en/africa.html" target="_blank"><?php p($l->t('The True Size of Africa')); ?> ↗</a>
        </p>
    </div>
</div>
