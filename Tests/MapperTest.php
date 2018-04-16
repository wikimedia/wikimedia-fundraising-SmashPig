<?php

namespace SmashPig\Tests;

use SmashPig\Core\Mapper;
use Symfony\Component\Yaml\Exception\ParseException;
use SmashPig\Core\SmashPigException;

/**
 * @group Mapper
 */
class MapperTest extends BaseSmashPigUnitTestCase {

	public function testMapReplacesString() {
		$testMapFilePath = 'Tests/data/test_map_string.yaml';
		$testMapVars['test_string_value'] = 'abc';

		$testOutput = Mapper::map(
			$testMapVars,
			$testMapFilePath
		);

		$this->assertEquals( [ 'test-string' => 'abc' ], $testOutput );
	}

	public function testMapReplacesArrayValue() {
		$testMapFilePath = 'Tests/data/test_map_array.yaml';
		$testMapVars['test_array_value'] = 'abc';

		$testOutput = Mapper::map( $testMapVars, $testMapFilePath );

		$expected = [
			'test-array' => [
				'test-array-value' => 'abc',
			],
		];

		$this->assertEquals( $expected, $testOutput );
	}

	public function testMapReplacesMultiDimensionalArrayValues() {
		$testMapFilePath = 'Tests/data/test_map_multi_array.yaml';

		$testMapVars = [
			'nested-array-value-one' => 'abc',
			'nested-array-value-two' => 'def',
			'nested-array-value-three' => 'ghi',
			'nested-array-value-four' => 'jkl',
		];

		$testOutput = Mapper::map( $testMapVars, $testMapFilePath );

		$expected = [
			'test-nested-array' =>
				[
					'test-nested-array-one' =>
						[
							'test-nested-child-two' =>
								[
									'test-nested-child-three' =>
										[
											'nested-array-value-one' => 'abc',
											'nested-array-value-two' => 'def',
										],
								],
						],
					'test-nested-array-two' =>
						[
							'test-nested-child-two' =>
								[
									'test-nested-child-three' =>
										[
											'nested-array-value-three' => 'ghi',
											'nested-array-value-four' => 'jkl',
										],
								],
						],
				],
		];

		$this->assertEquals( $expected, $testOutput );
	}

	public function testMapClearsUnsetVariablePlaceholders() {
		$testMapFilePath = 'Tests/data/test_map_unset.yaml';
		$emptyTestMapVars = [];

		$testOutput = Mapper::map( $emptyTestMapVars, $testMapFilePath );

		$expected = [
			'test-array' => [
				'test-array-value' => null,
			],
			'test-string' => null,
		];

		$this->assertEquals( $expected, $testOutput );
	}

	public function testMapReplacesDuplicateValues() {
		$testMapFilePath = 'Tests/data/test_map_duplicates.yaml';

		// this should be injected twice.
		$testMapVars['test_array_value'] = 'abc';
		$testOutput = Mapper::map( $testMapVars, $testMapFilePath );

		$expected = [
			'test-array' => [
				'test-array-value-one' => 'abc',
				'test-array-value-two' => 'abc',
			],
		];

		$this->assertEquals( $expected, $testOutput );
	}

	public function testMapToOutputFormatJson() {
		$testMapFilePath = 'Tests/data/test_map_array.yaml';
		$testMapVars['test_array_value'] = 'abc';

		$testOutput = Mapper::map(
			$testMapVars,
			$testMapFilePath,
			Mapper::FORMAT_JSON
		);

		$expected = '{"test-array":{"test-array-value":"abc"}}';

		$this->assertEquals( $expected, $testOutput );
	}

	public function testThrowsSmashPigExceptionIfCannotLoadMapFile() {
		$this->expectException( SmashPigException::class );

		$testMapFilePath = 'Tests/data/test_map_doesnt_exist.yaml';
		$testOutput = Mapper::map( [], $testMapFilePath );
	}

	public function testThrowsParserExceptionIfMapContainsInvalidYaml() {
		$this->expectException( ParseException::class );

		$testMapFilePath = 'Tests/data/test_map_not_valid_yaml.yaml';
		$testOutput = Mapper::map( [], $testMapFilePath );
	}

}
