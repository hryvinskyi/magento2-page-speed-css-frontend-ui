<?php
/**
 * Copyright (c) 2022. MageCloud.  All rights reserved.
 * @author: Volodymyr Hryvinskyi <mailto:volodymyr@hryvinskyi.com>
 */

declare(strict_types=1);

namespace Hryvinskyi\PageSpeedCssFrontendUi\Model;

use Hryvinskyi\PageSpeed\Model\Finder\Result\Tag;
use Hryvinskyi\PageSpeedApi\Api\Finder\CssInterface as CssFinderInterface;
use Hryvinskyi\PageSpeedApi\Api\Html\InsertStringBeforeHeadEndInterface;
use Hryvinskyi\PageSpeedApi\Api\Html\ReplaceIntoHtmlInterface;
use Hryvinskyi\PageSpeedApi\Model\ModificationInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Code\Minifier\Adapter\Css\CSSmin;
use Magento\Store\Model\ScopeInterface;

class MergeCriticalCss implements ModificationInterface
{
    private CssFinderInterface $cssFinder;
    private InsertStringBeforeHeadEndInterface $insertStringBeforeHeadEnd;
    private ReplaceIntoHtmlInterface $replaceIntoHtml;
    private ScopeConfigInterface $scopeConfig;
    private CSSmin $cssMinifier;

    /**
     * @param CssFinderInterface $cssFinder
     * @param InsertStringBeforeHeadEndInterface $insertStringBeforeHeadEnd
     * @param ReplaceIntoHtmlInterface $replaceIntoHtml
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        CssFinderInterface $cssFinder,
        InsertStringBeforeHeadEndInterface $insertStringBeforeHeadEnd,
        ReplaceIntoHtmlInterface $replaceIntoHtml,
        ScopeConfigInterface $scopeConfig,
        CSSmin $cssMinifier
    ) {
        $this->cssFinder = $cssFinder;
        $this->insertStringBeforeHeadEnd = $insertStringBeforeHeadEnd;
        $this->replaceIntoHtml = $replaceIntoHtml;
        $this->scopeConfig = $scopeConfig;
        $this->cssMinifier = $cssMinifier;
    }

    /**
     * @param $html
     * @return void
     */
    public function execute(&$html): void
    {
        if ($this->scopeConfig->isSetFlag('dev/css/use_css_critical_path', ScopeInterface::SCOPE_STORE) === false) {
            return;
        }

        $tagList = $this->cssFinder->findInline($html);
        $replaceData = [];
        foreach ($tagList as $tag) {
            /** @var Tag $tag */
            $replaceData[] = [
                'start' => $tag->getStart(),
                'end' => $tag->getEnd(),
                'content' => preg_replace('/(<(style)\b[^>]*>)(.*?)(<\/\2>)/is', "$3", $tag->getContent())
            ];
        }


        $resultString = '';
        foreach (array_reverse($replaceData) as $cutElData) {
            $resultString = $cutElData['content'] . "\n" . $resultString;
            $html = $this->replaceIntoHtml->execute($html, '', $cutElData['start'], $cutElData['end']);
        }
        $resultString = '<style>' . $this->cssMinifier->minify($resultString) . '</style>';

        $html = $this->insertStringBeforeHeadEnd->execute($resultString, $html);
    }
}
