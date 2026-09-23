<?php

declare(strict_types=1);

namespace Drupal\Tests\nexus\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\nexus\NexusResponseParser;
use PHPUnit\Framework\Attributes\Group;

/**
 * Tests NexusResponseParser in isolation — no Drupal bootstrap needed.
 *
 * Every provider-response validation path task 0103 asks for, covered
 * directly rather than only indirectly through
 * NexusInsightGeneratorTest's two representative cases.
 */
#[Group('hivelog')]
class NexusResponseParserTest extends UnitTestCase {

  /**
   * The parser under test.
   */
  protected NexusResponseParser $parser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->parser = new NexusResponseParser();
  }

  /**
   * Tests a fully valid response parses into exactly its own values.
   */
  public function testValidResponseParses(): void {
    $values = $this->parser->parse(json_encode([
      'verdict' => 'act_now',
      'recommendation' => 'Add a super',
      'signals' => '- Weight trending up steadily',
      'confidence' => 'medium',
    ]));

    $this->assertEquals('act_now', $values['verdict']);
    $this->assertEquals('Add a super', $values['recommendation']);
    $this->assertEquals('- Weight trending up steadily', $values['signals']);
    $this->assertEquals('medium', $values['confidence']);
  }

  /**
   * Tests `confidence` is optional and defaults to NULL, not an error.
   */
  public function testConfidenceIsOptional(): void {
    $values = $this->parser->parse(json_encode([
      'verdict' => 'all_clear',
      'recommendation' => 'No action needed',
      'signals' => '- Nothing unusual',
    ]));

    $this->assertNull($values['confidence']);
  }

  /**
   * Tests malformed JSON is rejected, not silently coerced.
   */
  public function testMalformedJsonRejected(): void {
    $this->expectException(\UnexpectedValueException::class);
    $this->parser->parse('this is not { valid json');
  }

  /**
   * Tests a JSON array (not object) is rejected.
   */
  public function testJsonArrayRejected(): void {
    $this->expectException(\UnexpectedValueException::class);
    $this->parser->parse(json_encode(['act_now', 'Add a super']));
  }

  /**
   * Tests an unrecognised verdict is rejected.
   */
  public function testUnrecognisedVerdictRejected(): void {
    $this->expectException(\UnexpectedValueException::class);
    $this->parser->parse(json_encode([
      'verdict' => 'maybe_do_something',
      'recommendation' => 'Test',
      'signals' => '- Test',
    ]));
  }

  /**
   * Tests a missing recommendation is rejected.
   */
  public function testMissingRecommendationRejected(): void {
    $this->expectException(\UnexpectedValueException::class);
    $this->parser->parse(json_encode([
      'verdict' => 'all_clear',
      'signals' => '- Test',
    ]));
  }

  /**
   * Tests an empty-string signals is rejected, not treated as "none".
   */
  public function testEmptySignalsRejected(): void {
    $this->expectException(\UnexpectedValueException::class);
    $this->parser->parse(json_encode([
      'verdict' => 'all_clear',
      'recommendation' => 'No action needed',
      'signals' => '   ',
    ]));
  }

  /**
   * Tests an unrecognised confidence value is rejected.
   */
  public function testUnrecognisedConfidenceRejected(): void {
    $this->expectException(\UnexpectedValueException::class);
    $this->parser->parse(json_encode([
      'verdict' => 'all_clear',
      'recommendation' => 'No action needed',
      'signals' => '- Nothing unusual',
      'confidence' => 'extremely-sure',
    ]));
  }

  /**
   * Tests a response wrapped in a ```json ... ``` fence still parses.
   *
   * Confirmed live against a real Anthropic call: the system prompt
   * explicitly asks for no markdown code fences, but the model sent one
   * anyway.
   */
  public function testJsonWrappedInLabelledCodeFenceParses(): void {
    $json = json_encode([
      'verdict' => 'act_now',
      'recommendation' => 'Add a super',
      'signals' => '- Weight trending up steadily',
    ]);
    $values = $this->parser->parse("```json\n$json\n```");

    $this->assertEquals('act_now', $values['verdict']);
    $this->assertEquals('Add a super', $values['recommendation']);
  }

  /**
   * Tests a response wrapped in a plain ``` ... ``` fence (no language tag).
   */
  public function testJsonWrappedInPlainCodeFenceParses(): void {
    $json = json_encode([
      'verdict' => 'all_clear',
      'recommendation' => 'No action needed',
      'signals' => '- Nothing unusual',
    ]);
    $values = $this->parser->parse("```\n$json\n```");

    $this->assertEquals('all_clear', $values['verdict']);
  }

  /**
   * Tests surrounding whitespace around a fenced response is tolerated.
   */
  public function testFencedResponseWithSurroundingWhitespaceParses(): void {
    $json = json_encode([
      'verdict' => 'inspect_soon',
      'recommendation' => 'Check for swarm cells',
      'signals' => '- Population strong, several supers full',
    ]);
    $values = $this->parser->parse("  \n```json\n$json\n```\n  ");

    $this->assertEquals('inspect_soon', $values['verdict']);
  }

}
