<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 *
 * @category Piwik_Plugins
 * @package UserCountryMap
 */
namespace Piwik\Plugins\UserCountryMap;

use Exception;
use Piwik\API\Request;
use Piwik\Piwik;
use Piwik\Common;
use Piwik\Plugins\Goals\API;
use Piwik\ViewDataTable;
use Piwik\View;
use Piwik\Site;
use Piwik\Config;

/**
 *
 * @package UserCountryMap
 */
class Controller extends \Piwik\Controller
{

    // By default plot up to the last 30 days of visitors on the map, for low traffic sites
    const REAL_TIME_WINDOW = 'last30';

    public function visitorMap($fetch = false, $segmentOverride = false)
    {
        $this->checkUserCountryPluginEnabled();

        $idSite = Common::getRequestVar('idSite', 1, 'int');
        Piwik::checkUserHasViewAccess($idSite);

        $period = Common::getRequestVar('period');
        $date = Common::getRequestVar('date');
        $segment = $segmentOverride ?: Request::getRawSegmentFromRequest() ?: '';
        $token_auth = Piwik::getCurrentUserTokenAuth();

        $view = new View('@UserCountryMap/visitorMap');

        // request visits summary
        $request = new Request(
            'method=VisitsSummary.get&format=PHP'
                . '&idSite=' . $idSite
                . '&period=' . $period
                . '&date=' . $date
                . '&segment=' . $segment
                . '&token_auth=' . $token_auth
                . '&filter_limit=-1'
        );
        $config = array();
        $config['visitsSummary'] = unserialize($request->process());
        $config['countryDataUrl'] = $this->_report('UserCountry', 'getCountry',
            $idSite, $period, $date, $token_auth, false, $segment);
        $config['regionDataUrl'] = $this->_report('UserCountry', 'getRegion',
            $idSite, $period, $date, $token_auth, true, $segment);
        $config['cityDataUrl'] = $this->_report('UserCountry', 'getCity',
            $idSite, $period, $date, $token_auth, true, $segment);
        $config['countrySummaryUrl'] = $this->getApiRequestUrl('VisitsSummary', 'get',
            $idSite, $period, $date, $token_auth, true, $segment);
        $view->defaultMetric = 'nb_visits';

        // some translations
        $view->localeJSON = Common::json_encode(array(
                                                     'nb_visits'            => Piwik_Translate('VisitsSummary_NbVisits'),
                                                     'one_visit'            => Piwik_Translate('General_OneVisit'),
                                                     'no_visit'             => Piwik_Translate('UserCountryMap_NoVisit'),
                                                     'nb_actions'           => Piwik_Translate('VisitsSummary_NbActionsDescription'),
                                                     'nb_actions_per_visit' => Piwik_Translate('VisitsSummary_NbActionsPerVisit'),
                                                     'bounce_rate'          => Piwik_Translate('VisitsSummary_NbVisitsBounced'),
                                                     'avg_time_on_site'     => Piwik_Translate('VisitsSummary_AverageVisitDuration'),
                                                     'and_n_others'         => Piwik_Translate('UserCountryMap_AndNOthers'),
                                                     'no_data'              => Piwik_Translate('CoreHome_ThereIsNoDataForThisReport')
                                                ));

        $view->reqParamsJSON = $this->getEnrichedRequest($params = array(
            'period'                      => $period,
            'idSite'                      => $idSite,
            'date'                        => $date,
            'segment'                     => $segment,
            'token_auth'                  => $token_auth,
            'enable_filter_excludelowpop' => 1,
            'filter_excludelowpop_value'  => -1
        ));

        $view->metrics = $config['metrics'] = $this->getMetrics($idSite, $period, $date, $token_auth);
        $config['svgBasePath'] = 'plugins/UserCountryMap/svg/';
        $config['mapCssPath'] = 'plugins/UserCountryMap/stylesheets/map.css';
        $view->config = Common::json_encode($config);
        $view->noData = empty($config['visitsSummary']['nb_visits']);

        if ($fetch) {
            return $view->render();
        } else {
            echo $view->render();
        }
    }

    /**
     * Used to build the report Visitor > Real time map
     */
    public function realtimeWorldMap()
    {
        return $this->realtimeMap($standalone = true);
    }

    /**
     * @param bool $standalone When set to true, the Top controls will be hidden to provide better full screen view
     * @param bool $fetch
     * @param bool|string $segmentOverride
     *
     * @return string
     */
    public function realtimeMap($standalone = false, $fetch = false, $segmentOverride = false)
    {
        $this->checkUserCountryPluginEnabled();

        $idSite = Common::getRequestVar('idSite', 1, 'int');
        Piwik::checkUserHasViewAccess($idSite);

        $token_auth = Piwik::getCurrentUserTokenAuth();
        $view = new View('@UserCountryMap/realtimeMap');

        $view->mapIsStandaloneNotWidget = $standalone;

        $view->metrics = $this->getMetrics($idSite, 'range', self::REAL_TIME_WINDOW, $token_auth);
        $view->defaultMetric = 'nb_visits';
        $view->liveRefreshAfterMs = (int)Config::getInstance()->General['live_widget_refresh_after_seconds'] * 1000;

        $goals = API::getInstance()->getGoals($idSite);
        $site = new Site($idSite);
        $view->hasGoals = !empty($goals) || $site->isEcommerceEnabled() ? 'true' : 'false';

        // maximum number of visits to be displayed in the map
        $view->maxVisits = Common::getRequestVar('format_limit', 100, 'int');

        // some translations
        $view->localeJSON = json_encode(array(
                                             'nb_actions'       => Piwik_Translate('VisitsSummary_NbActionsDescription'),
                                             'local_time'       => Piwik_Translate('VisitTime_ColumnLocalTime'),
                                             'from'             => Piwik_Translate('General_FromReferrer'),
                                             'seconds'          => Piwik_Translate('UserCountryMap_Seconds'),
                                             'seconds_ago'      => Piwik_Translate('UserCountryMap_SecondsAgo'),
                                             'minutes'          => Piwik_Translate('UserCountryMap_Minutes'),
                                             'minutes_ago'      => Piwik_Translate('UserCountryMap_MinutesAgo'),
                                             'hours'            => Piwik_Translate('UserCountryMap_Hours'),
                                             'hours_ago'        => Piwik_Translate('UserCountryMap_HoursAgo'),
                                             'days_ago'         => Piwik_Translate('UserCountryMap_DaysAgo'),
                                             'actions'          => Piwik_Translate('VisitsSummary_NbPageviewsDescription'),
                                             'searches'         => Piwik_Translate('UserCountryMap_Searches'),
                                             'goal_conversions' => Piwik_Translate('UserCountryMap_GoalConversions'),
                                        ));

        $segment = $segmentOverride ?: Request::getRawSegmentFromRequest() ?: '';
        $view->reqParamsJSON = $this->getEnrichedRequest(array(
                                                              'period'     => 'range',
                                                              'idSite'     => $idSite,
                                                              'date'       => self::REAL_TIME_WINDOW,
                                                              'segment'    => $segment,
                                                              'token_auth' => $token_auth,
                                                         ));

        if ($fetch) {
            return $view->render();
        } else {
            echo $view->render();
        }
    }

    private function getEnrichedRequest($params)
    {
        $params['format'] = 'json';
        $params['showRawMetrics'] = 1;
        if (empty($params['segment'])) {
            $segment = \Piwik\API\Request::getRawSegmentFromRequest();
            if (!empty($segment)) {
                $params['segment'] = urldecode($segment);
            }
        }

        return Common::json_encode($params);
    }

    private function checkUserCountryPluginEnabled()
    {
        if (!\Piwik\PluginsManager::getInstance()->isPluginActivated('UserCountry')) {
            throw new Exception(Piwik_Translate('General_Required', 'Plugin UserCountry'));
        }
    }

    private function getMetrics($idSite, $period, $date, $token_auth)
    {
        $request = new Request(
            'method=API.getMetadata&format=PHP'
                . '&apiModule=UserCountry&apiAction=getCountry'
                . '&idSite=' . $idSite
                . '&period=' . $period
                . '&date=' . $date
                . '&token_auth=' . $token_auth
                . '&filter_limit=-1'
        );
        $metaData = $request->process();

        $metrics = array();
        foreach ($metaData[0]['metrics'] as $id => $val) {
            if (Common::getRequestVar('period') == 'day' || $id != 'nb_uniq_visitors') {
                $metrics[] = array($id, $val);
            }
        }
        foreach ($metaData[0]['processedMetrics'] as $id => $val) {
            $metrics[] = array($id, $val);
        }
        return $metrics;
    }

    private function getApiRequestUrl($module, $action, $idSite, $period, $date, $token_auth, $filter_by_country = false, $segmentOverride = false)
    {
        // use processed reports
        $url = "?module=" . $module
            . "&method=" . $module . "." . $action . "&format=JSON"
            . "&idSite=" . $idSite
            . "&period=" . $period
            . "&date=" . $date
            . "&token_auth=" . $token_auth
            . "&segment=" . ($segmentOverride ?: Request::getRawSegmentFromRequest())
            . "&enable_filter_excludelowpop=1"
            . "&showRawMetrics=1";

        if ($filter_by_country) {
            $url .= "&filter_column=country"
                . "&filter_sort_column=nb_visits"
                . "&filter_limit=-1"
                . "&filter_pattern=";
        } else {
            $url .= "&filter_limit=-1";
        }
        return $url;
    }

    private function _report($module, $action, $idSite, $period, $date, $token_auth, $filter_by_country = false, $segmentOverride = false)
    {
        return $this->getApiRequestUrl('API', 'getProcessedReport&apiModule=' . $module . '&apiAction=' . $action,
                                       $idSite, $period, $date, $token_auth, $filter_by_country, $segmentOverride);
    }
}
