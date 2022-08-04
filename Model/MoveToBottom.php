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
use Hryvinskyi\PageSpeedCss\Api\CanCssMoveToBottomInterface;
use Hryvinskyi\PageSpeedCss\Api\ConfigInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class MoveToBottom implements ModificationInterface
{
    private CssFinderInterface $cssFinder;
    private InsertStringBeforeHeadEndInterface $insertStringBeforeHeadEnd;
    private ReplaceIntoHtmlInterface $replaceIntoHtml;
    private CanCssMoveToBottomInterface $canCssMoveToBottom;
    private ScopeConfigInterface $scopeConfig;
    private ConfigInterface $config;

    /**
     * @param CssFinderInterface $cssFinder
     * @param InsertStringBeforeHeadEndInterface $insertStringBeforeHeadEnd
     * @param ReplaceIntoHtmlInterface $replaceIntoHtml
     * @param CanCssMoveToBottomInterface $canCssMoveToBottom
     * @param ScopeConfigInterface $scopeConfig
     * @param ConfigInterface $config
     */
    public function __construct(
        CssFinderInterface $cssFinder,
        InsertStringBeforeHeadEndInterface $insertStringBeforeHeadEnd,
        ReplaceIntoHtmlInterface $replaceIntoHtml,
        CanCssMoveToBottomInterface $canCssMoveToBottom,
        ScopeConfigInterface $scopeConfig,
        ConfigInterface $config
    ) {
        $this->cssFinder = $cssFinder;
        $this->insertStringBeforeHeadEnd = $insertStringBeforeHeadEnd;
        $this->replaceIntoHtml = $replaceIntoHtml;
        $this->canCssMoveToBottom = $canCssMoveToBottom;
        $this->scopeConfig = $scopeConfig;
        $this->config = $config;
    }

    /**
     * @param $html
     * @return void
     */
    public function execute(&$html): void
    {
        if ($this->scopeConfig->isSetFlag('dev/css/use_css_critical_path', ScopeInterface::SCOPE_STORE) === false
            || $this->config->isEnableMoveToBottom() === false) {
            return;
        }

        $tagList = $this->cssFinder->findExternal($html);
        $cutData = [];
        foreach ($tagList as $tag) {
            if ($this->canCssMoveToBottom->execute($tag) === false) {
                continue;
            }
            /** @var Tag $tag */
            $cutTagData = [
                'start' => $tag->getStart(),
                'end' => $tag->getEnd(),
                'content' => $tag->getContent()
            ];

            $cutData[] = $cutTagData;
        }

        $resultString = "";
        foreach (array_reverse($cutData) as $cutElData) {
            $resultString = $cutElData['content'] . "\n" . $resultString;
            $html = $this->replaceIntoHtml->execute($html, '', $cutElData['start'], $cutElData['end']);
        }

        $html = $this->insertStringBeforeHeadEnd->execute($resultString, $html);
    }
}
