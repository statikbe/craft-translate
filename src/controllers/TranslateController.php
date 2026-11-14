<?php
/**
 * Translate plugin for Craft CMS 3.x
 *
 * Translation management plugin for Craft CMS
 *
 * @link      https://www.statik.be
 * @copyright Copyright (c) 2017 Statik.be
 */


namespace statikbe\translate\controllers;

use Craft;
use craft\helpers\FileHelper;
use craft\helpers\StringHelper;
use craft\web\Controller as BaseController;
use statikbe\translate\elements\Translate as ElementTranslate;
use statikbe\translate\helpers\PostedTranslations;
use statikbe\translate\Translate;
use yii\web\BadRequestHttpException;

class TranslateController extends BaseController
{
    /**
     * Every action needs access to the Translate section of the control panel.
     *
     * @inheritdoc
     */
    public function beforeAction($action): bool
    {
        $this->requirePermission('accessPlugin-translate');

        return parent::beforeAction($action);
    }

    /**
     * Download translations.
     *
     * @throws \yii\base\Exception
     */
    public function actionDownload(): \yii\web\Response
    {
        $this->requirePostRequest();
        $siteId = Craft::$app->request->getRequiredBodyParam('siteId');
        $sourceKey = Craft::$app->request->getRequiredBodyParam('sourceKey');
        $statusSubString = 'status:';
        $templateSubString = 'templates/templates:';
        $pluginSubString = 'plugins/plugins:';
        $allTemplatesSubString = 'all-templates:';
        $categorySubString = 'category:';

        $sources = [];
        $query = ElementTranslate::find();
        $query->status = null;
        $pluginName = null;

        // Get params
        // Process Template Status
        if (str_contains($sourceKey, $statusSubString)) {
            $criteria = explode($statusSubString, $sourceKey);
            $query->status = $criteria[1] ?? null;
            $sources[] = Craft::$app->path->getSiteTemplatesPath();
        }

        // Process Templates Status
        if (str_contains($sourceKey, $templateSubString)) {
            // Never scan a path from the request
            $sources[] = Craft::$app->path->getSiteTemplatesPath();
        }

        // Process Plugin Status
        if (str_contains($sourceKey, $pluginSubString)) {
            $criteria = explode($pluginSubString, $sourceKey);
            $plugin = Craft::$app->plugins->getPlugin($criteria[1]);
            $pluginName = $plugin->getHandle();
            $sources[] = $plugin->getBasePath() ?? '';
            $query->pluginHandle = $plugin->getHandle();
        }

        // All templates
        if (str_contains($sourceKey, $allTemplatesSubString)) {
            $sources[] = Craft::$app->path->getSiteTemplatesPath();
        }

        // Templates for a translation category
        if (str_starts_with($sourceKey, $categorySubString)) {
            $category = substr($sourceKey, strlen($categorySubString));
            if (!in_array($category, Translate::getInstance()->translate->getSiteCategories(), true)) {
                throw new BadRequestHttpException('Invalid translation category');
            }
            $query->category = $category;
            $pluginName = $category;
            $sources[] = Craft::$app->path->getSiteTemplatesPath();
        }

        if (empty($sources)) {
            throw new BadRequestHttpException('Invalid source');
        }

        $site = Craft::$app->getSites()->getSiteById($siteId);

        // @todo add support for search
        $query->search = false;
        $query->siteId = $siteId;
        $query->source = $sources;

        // Get occurences
        $occurences = Translate::getInstance()->translate->get($query);

        // Re-order data
        $data = StringHelper::convertToUTF8('"' . Craft::t('translate','Source {language}',['language' => $site->language]) . '","' . Craft::t('translate','Translation') . "\"\r\n");

        foreach ($occurences as $element) {
            $data .= StringHelper::convertToUTF8('"' . $element->original . '","' . $element->translation . "\"\r\n");
        }

        $info = Craft::$app->getInfo();
        //name in info bestaat niet. => Nu vast ingevuld met import
        $systemName = FileHelper::sanitizeFilename(
//            $pluginName ?? $info->name,
            $pluginName ?? "import",
            [
                'asciiOnly' => true,
                'separator' => '_',
            ]
        );
        $date = date('YmdHis');
        $primarySite = Craft::$app->getSites()->getPrimarySite();
        $sourceTo = $primarySite->language . '_to_' . $site->language;
        $fileName = strtolower($systemName . '_translations_' . $sourceTo . '_' . $date);

        return $this->response->sendContentAsFile($data, $fileName . '.csv', ['mimeType' => 'text/csv']);
    }

    /**
     * Save translations.
     *
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionSave(): \yii\web\Response
    {
        $this->requireAcceptsJson();
        $response = [
            'success' => true,
            'errors' => [],
        ];
        $siteId = Craft::$app->request->getBodyParam('siteId');
        if (!$siteId) {
            $siteId = Craft::$app->getSites()->getPrimarySite()->id;
        }
        $sourceKey = Craft::$app->request->getRequiredBodyParam('sourceKey');
        $site = Craft::$app->getSites()->getSiteById((int)$siteId);

        $pluginSubString = 'modules/plugins:';
        $categorySubString = 'category:';
        $translatePath = null;
        $sitePath = Craft::$app->getPath()->getSiteTranslationsPath();

        // Process translation category
        if (str_starts_with($sourceKey, $categorySubString)) {
            $category = substr($sourceKey, strlen($categorySubString));
            if (!in_array($category, Translate::getInstance()->translate->getSiteCategories(), true)) {
                throw new BadRequestHttpException('Invalid translation category');
            }
            $translatePath = Translate::getInstance()->translate->getSitePath($site->language, $category);
        }

        // Process Plugin Status
        if (str_contains($sourceKey, $pluginSubString)) {
            $criteria = explode($pluginSubString, $sourceKey);
            $plugin = Craft::$app->plugins->getPlugin($criteria[1]);
            if ($plugin) {
                $pluginHandle = $plugin->getHandle();
                $translatePath = $plugin->getBasePath() ?? null;
                if ($translatePath && $pluginHandle) {
                    $translatePath = $sitePath . DIRECTORY_SEPARATOR . $site->language . DIRECTORY_SEPARATOR . $pluginHandle . '.php';
                }
            } else {
                // A registered module: its id becomes a file name, so no paths
                if (!preg_match('/^[\w-]+$/', $criteria[1])) {
                    throw new BadRequestHttpException('Invalid plugin');
                }
                $translatePath = $sitePath . DIRECTORY_SEPARATOR . $site->language . DIRECTORY_SEPARATOR . $criteria[1] . '.php';
            }
        }
        $translations = PostedTranslations::messages(Craft::$app->request->getRequiredBodyParam('translation'));

        if (Translate::getInstance()->getSettings()->storeInDatabase) {
            // Only the values that differ from the files are stored, on top of them
            $category = Translate::getInstance()->translate->categoryForSourceKey($sourceKey);
            $userId = Craft::$app->getUser()->getId();
            $response += Translate::getInstance()->overrides->save($category, $site->language, $translations, $userId ? (int)$userId : null);

            return $this->asJson($response);
        }

        // Save to translation file
        Translate::getInstance()->translate->set($site->language, $translations, $translatePath);

        // Redirect back to page
        return $this->asJson($response);
    }

    /**
     * Restores the default of a translation: removes the override an editor stored (with `storeInDatabase` on).
     *
     * @throws \yii\web\BadRequestHttpException
     */
    public function actionRestore(): \yii\web\Response
    {
        $this->requirePostRequest();
        $this->requireAcceptsJson();

        if (!Translate::getInstance()->getSettings()->storeInDatabase) {
            throw new BadRequestHttpException('Translations aren\'t stored in the database.');
        }

        $siteId = (int)($this->request->getBodyParam('siteId') ?: Craft::$app->getSites()->getPrimarySite()->id);
        $site = Craft::$app->getSites()->getSiteById($siteId);
        if (!$site) {
            throw new BadRequestHttpException('Invalid site');
        }
        $category = Translate::getInstance()->translate->categoryForSourceKey((string)$this->request->getRequiredBodyParam('sourceKey'));
        $message = (string)$this->request->getRequiredBodyParam('message');

        $restored = Translate::getInstance()->overrides->restore($category, $site->language, $message);

        return $this->asJson(['success' => true, 'restored' => $restored]);
    }
}
