<?php
namespace OCA\Maps\Settings;

use bantu\IniGetWrapper\IniGetWrapper;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\IRequest;
use OCP\IL10N;
use OCP\IConfig;
use OCP\Settings\ISettings;
use OCP\IInitialStateService;
use OCP\Util;
use OCP\IURLGenerator;

class AdminSettings implements ISettings {

    /** @var IniGetWrapper */
    private $iniWrapper;

    /** @var IRequest */
    private $request;
    private $config;
    private $dataDirPath;
    private $urlGenerator;
    private $l;

    /** @var IInitialStateService */
    private $initialStateService

    public function __construct(
                        IniGetWrapper $iniWrapper,
                        IL10N $l,
                        IRequest $request,
                        IConfig $config,
                        IURLGenerator $urlGenerator,
						IInitialStateService $initialStateService) {
        $this->urlGenerator = $urlGenerator;
        $this->iniWrapper = $iniWrapper;
        $this->request = $request;
        $this->l = $l;
        $this->config = $config;
        $this->initialStateService = $initialStateService;
    }

    /**
     * @return TemplateResponse
     */
    public function getForm() {
        $keys = [
            'osrmCarURL',
            'osrmBikeURL',
            'osrmFootURL',
            'osrmDEMO',
            'graphhopperAPIKEY',
            'mapboxAPIKEY',
            'graphhopperURL'
        ];
        foreach ($keys as $k) {
            $data = $this->config->getAppValue('maps', $k, '');
            $this->initialStateService->provideInitialState('maps', $k, $data);
        }

        return new TemplateResponse('maps', 'adminSettings');
    }

    /**
     * @return string the section ID, e.g. 'sharing'
     */
    public function getSection() {
        return 'additional';
    }

    /**
     * @return int whether the form should be rather on the top or bottom of
     * the admin section. The forms are arranged in ascending order of the
     * priority values. It is required to return a value between 0 and 100.
     *
     * E.g.: 70
     */
    public function getPriority() {
        return 5;
    }

}
