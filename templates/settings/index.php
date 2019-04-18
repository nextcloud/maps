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
    </div>
</div>
