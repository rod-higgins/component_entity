<?php

namespace Drupal\Tests\component_entity\Unit;

use Drupal\Tests\UnitTestCase;
use Drupal\component_entity\Exception\InvalidComponentException;
use Drupal\component_entity\Entity\ComponentEntityInterface;

/**
 * Tests the InvalidComponentException class.
 *
 * @group component_entity
 * @coversDefaultClass \Drupal\component_entity\Exception\InvalidComponentException
 */
class InvalidComponentExceptionTest extends UnitTestCase {

  /**
   * Tests basic exception creation.
   *
   * @covers ::__construct
   */
  public function testBasicException() {
    $exception = new InvalidComponentException('Test error message');
    
    $this->assertInstanceOf(InvalidComponentException::class, $exception);
    $this->assertEquals('Test error message', $exception->getMessage());
  }

  /**
   * Tests exception with component entity.
   *
   * @covers ::__construct
   * @covers ::getComponent
   */
  public function testExceptionWithComponent() {
    $component = $this->createMock(ComponentEntityInterface::class);
    
    $exception = new InvalidComponentException(
      'Component is invalid',
      $component,
      InvalidComponentException::INVALID_FIELD_VALUE
    );
    
    $this->assertSame($component, $exception->getComponent());
    $this->assertEquals(InvalidComponentException::INVALID_FIELD_VALUE, $exception->getErrorType());
  }

  /**
   * Tests missing required fields exception.
   *
   * @covers ::missingRequiredFields
   */
  public function testMissingRequiredFieldsException() {
    $component = $this->createMock(ComponentEntityInterface::class);
    $component->expects($this->once())
      ->method('label')
      ->willReturn('Test Component');
    $component->expects($this->once())
      ->method('bundle')
      ->willReturn('test_type');

    $missing_fields = ['field_title', 'field_description'];
    
    $exception = InvalidComponentException::missingRequiredFields($component, $missing_fields);
    
    $this->assertInstanceOf(InvalidComponentException::class, $exception);
    $this->assertStringContainsString('Test Component', $exception->getMessage());
    $this->assertStringContainsString('field_title', $exception->getMessage());
    $this->assertStringContainsString('field_description', $exception->getMessage());
    $this->assertEquals(InvalidComponentException::MISSING_REQUIRED_FIELD, $exception->getErrorType());
    $this->assertEquals($missing_fields, $exception->getDetails()['missing_fields']);
  }

  /**
   * Tests invalid field value exception.
   *
   * @covers ::invalidFieldValue
   */
  public function testInvalidFieldValueException() {
    $component = $this->createMock(ComponentEntityInterface::class);
    $component->expects($this->once())
      ->method('label')
      ->willReturn('Test Component');

    $exception = InvalidComponentException::invalidFieldValue(
      $component,
      'field_number',
      'not_a_number',
      'Value must be numeric'
    );
    
    $this->assertInstanceOf(InvalidComponentException::class, $exception);
    $this->assertStringContainsString('field_number', $exception->getMessage());
    $this->assertStringContainsString('Value must be numeric', $exception->getMessage());
    $this->assertEquals('field_number', $exception->getFieldName());
    $this->assertEquals(InvalidComponentException::INVALID_FIELD_VALUE, $exception->getErrorType());
  }

  /**
   * Tests invalid render method exception.
   *
   * @covers ::invalidRenderMethod
   */
  public function testInvalidRenderMethodException() {
    $component = $this->createMock(ComponentEntityInterface::class);
    $component->expects($this->once())
      ->method('label')
      ->willReturn('Test Component');
    
    $exception = InvalidComponentException::invalidRenderMethod($component, 'invalid_method');
    
    $this->assertInstanceOf(InvalidComponentException::class, $exception);
    $this->assertStringContainsString('invalid_method', $exception->getMessage());
    $this->assertStringContainsString('twig, react', $exception->getMessage());
    $this->assertEquals(InvalidComponentException::INVALID_RENDER_METHOD, $exception->getErrorType());
    $this->assertEquals('invalid_method', $exception->getDetails()['method']);
  }

  /**
   * Tests missing SDC component exception.
   *
   * @covers ::missingSdcComponent
   */
  public function testMissingSdcComponentException() {
    $component = $this->createMock(ComponentEntityInterface::class);
    $component->expects($this->once())
      ->method('bundle')
      ->willReturn('test_type');

    $exception = InvalidComponentException::missingSdcComponent($component, 'test:missing_component');
    
    $this->assertInstanceOf(InvalidComponentException::class, $exception);
    $this->assertStringContainsString('test:missing_component', $exception->getMessage());
    $this->assertEquals(InvalidComponentException::MISSING_SDC_COMPONENT, $exception->getErrorType());
    $this->assertEquals('test:missing_component', $exception->getDetails()['sdc_id']);
  }

  /**
   * Tests circular reference exception.
   *
   * @covers ::circularReference
   */
  public function testCircularReferenceException() {
    $component = $this->createMock(ComponentEntityInterface::class);
    $component->expects($this->once())
      ->method('id')
      ->willReturn('123');

    $chain = ['component_1', 'component_2', 'component_1'];
    
    $exception = InvalidComponentException::circularReference($component, $chain);
    
    $this->assertInstanceOf(InvalidComponentException::class, $exception);
    $this->assertStringContainsString('123', $exception->getMessage());
    $this->assertStringContainsString('circular', strtolower($exception->getMessage()));
    $this->assertEquals(InvalidComponentException::CIRCULAR_REFERENCE, $exception->getErrorType());
    $this->assertEquals($chain, $exception->getDetails()['reference_chain']);
  }

  /**
   * Tests exception with violations.
   *
   * @covers ::getViolations
   */
  public function testExceptionWithViolations() {
    $violations = [
      'field_1' => 'Violation 1',
      'field_2' => 'Violation 2',
    ];

    $exception = new InvalidComponentException(
      'Validation failed',
      null,
      InvalidComponentException::SCHEMA_VALIDATION_FAILED,
      $violations
    );

    $this->assertEquals($violations, $exception->getViolations());
    $this->assertEquals(InvalidComponentException::SCHEMA_VALIDATION_FAILED, $exception->getErrorType());
  }

  /**
   * Tests exception details.
   *
   * @covers ::getDetails
   */
  public function testExceptionDetails() {
    $details = [
      'custom_key' => 'custom_value',
      'error_code' => 42,
    ];

    $exception = new InvalidComponentException(
      'Error with details',
      null,
      InvalidComponentException::INVALID_PROPS,
      [],
      null,
      $details
    );

    $this->assertEquals($details, $exception->getDetails());
    $this->assertEquals('custom_value', $exception->getDetails()['custom_key']);
    $this->assertEquals(42, $exception->getDetails()['error_code']);
  }

}