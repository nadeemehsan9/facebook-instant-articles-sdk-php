<?php
/**
 * Copyright (c) 2016-present, Facebook, Inc.
 * All rights reserved.
 *
 * This source code is licensed under the license found in the
 * LICENSE file in the root directory of this source tree.
 */
namespace Facebook\InstantArticles\Transformer;

use Facebook\InstantArticles\Elements\InstantArticle;
use Facebook\InstantArticles\Elements\Header;
use Facebook\InstantArticles\Elements\Time;
use Facebook\InstantArticles\Elements\Author;

class WPTest extends \PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        \Logger::configure(
            [
                'rootLogger' => [
                    'appenders' => ['facebook-instantarticles-transformer']
                ],
                'appenders' => [
                    'facebook-instantarticles-transformer' => [
                        'class' => 'LoggerAppenderConsole',
                        'threshold' => 'DEBUG',
                        'layout' => [
                            'class' => 'LoggerLayoutSimple'
                        ]
                    ]
                ]
            ]
        );
    }

    public function testTransformerLikeWPContent()
    {
        $json_file = file_get_contents(__DIR__ . '/wp-rules.json');

        $instant_article = InstantArticle::create();
        $transformer = new Transformer();
        $transformer->loadRules($json_file);

        $html_file = file_get_contents(__DIR__ . '/wp.html');

        libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $document->loadHTML($html_file);
        libxml_use_internal_errors(false);

        $instant_article
            ->withCanonicalURL('http://localhost/article')
            ->withHeader(
                Header::create()
                    ->withTitle('Peace on <b>earth</b>')
                    ->addAuthor(Author::create()->withName('bill'))
                    ->withPublishTime(Time::create(Time::PUBLISHED)->withDatetime(
                        \DateTime::createFromFormat(
                            'j-M-Y G:i:s',
                            '12-Apr-2016 19:46:51'
                        )
                    ))
            );

        $transformer->transform($instant_article, $document);
        $instant_article->addMetaProperty('op:generator:version', '1.0.0');
        $instant_article->addMetaProperty('op:generator:transformer:version', '1.0.0');
        $result = $instant_article->render('', true)."\n";
        $expected = file_get_contents(__DIR__ . '/wp-ia.xml');

        foreach ($transformer->getWarnings() as $warning) {
          var_dump($warning->__toString());
        }


        $this->assertEquals($expected, $result);
        // there must be 3 warnings related to <img> inside <li> that is not supported by IA
        $this->assertEquals(3, count($transformer->getWarnings()));
    }
}
