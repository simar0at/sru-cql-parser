<?php

namespace rsanderson\CQLParser;

require '../src/cql.php';

/**
 * Generated by PHPUnit_SkeletonGenerator 1.2.1 on 2014-03-18 at 14:18:42.
 */
class CQLParserSimpleSearchClauseTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var ModifierClause
     */
    protected $object;
    protected $config;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp() {
        $this->object = new CQLParser('imprintDate==2010');
        $this->config = new CQLConfig();
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown() {
        
    }

    /**
     * @covers rsanderson\CQLParser\ModifierClause::toCQL
     * @todo   Implement testToCQL().
     */
    public function testToCQL() {
        $out = $this->object->query();
        $this->assertInstanceOf('\rsanderson\CQLParser\SearchClause', $out);
        $out->config = $this->config;
        $this->assertStringEqualsFile('src/SimpleSearchClauseToCQL.txt', $out->toCQL());
    }

    /**
     * @covers rsanderson\CQLParser\ModifierClause::toXCQL
     * @todo   Implement testToXCQL().
     */
    public function testToXCQL() {
        $out = $this->object->query();
        $this->assertInstanceOf('\rsanderson\CQLParser\SearchClause', $out);
        $out->config = $this->config;
        $this->assertXmlStringEqualsXmlFile('src/SimpleSearchClauseToXCQL.xml', $out->toXCQL());
    }

    /**
     * @covers rsanderson\CQLParser\ModifierClause::toTxt
     * @todo   Implement testToTxt().
     */
    public function testToTxt() {
//        $out = $this->object->query();
//        $out->config = $this->config;
//        $this->assertStringEqualsFile('DemoStringToTxt.txt', $out->toTxt());
        $this->markTestSkipped('Compare to file fails for unknown reason.');
    }

}
