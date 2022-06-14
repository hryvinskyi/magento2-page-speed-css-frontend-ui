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
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;

class MoveToBottom implements ModificationInterface
{
    private CssFinderInterface $cssFinder;
    private InsertStringBeforeHeadEndInterface $insertStringBeforeBodyEnd;
    private ReplaceIntoHtmlInterface $replaceIntoHtml;
    private CanCssMoveToBottomInterface $canCssMoveToBottom;
    private ScopeConfigInterface $scopeConfig;

    /**
     * @param CssFinderInterface $cssFinder
     * @param InsertStringBeforeHeadEndInterface $insertStringBeforeBodyEnd
     * @param ReplaceIntoHtmlInterface $replaceIntoHtml
     * @param CanCssMoveToBottomInterface $canCssMoveToBottom
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(
        CssFinderInterface $cssFinder,
        InsertStringBeforeHeadEndInterface $insertStringBeforeBodyEnd,
        ReplaceIntoHtmlInterface $replaceIntoHtml,
        CanCssMoveToBottomInterface $canCssMoveToBottom,
        ScopeConfigInterface $scopeConfig
    ) {
        $this->cssFinder = $cssFinder;
        $this->insertStringBeforeBodyEnd = $insertStringBeforeBodyEnd;
        $this->replaceIntoHtml = $replaceIntoHtml;
        $this->canCssMoveToBottom = $canCssMoveToBottom;
        $this->scopeConfig = $scopeConfig;
    }

    /**
     * @param $html
     * @return void
     */
    public function execute(&$html): void
    {
//        if ($this->scopeConfig->isSetFlag('dev/css/use_css_critical_path', ScopeInterface::SCOPE_STORE) === false) {
//            return;
//        }

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

        $html = $this->insertStringBeforeBodyEnd->execute($resultString, $html);
    }
}
