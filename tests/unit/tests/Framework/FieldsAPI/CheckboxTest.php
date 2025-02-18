<?php

namespace unit\tests\Framework\FieldsAPI;

use Give\Framework\FieldsAPI\Checkbox;
use Give_Unit_Test_Case;

class CheckboxTest extends Give_Unit_Test_Case {
	/**
	 * @since 2.19.0
	 */
	public function testChecked() {
		// Default is true
		$checkbox = Checkbox::make( 'test' )->checked();
		self::assertTrue( $checkbox->isChecked() );

		// Can pass a truthy value
		$checkbox = Checkbox::make( 'test' )->checked( true );
		self::assertTrue( $checkbox->isChecked() );

		$checkbox = Checkbox::make( 'test' )->checked( 1 );
		self::assertTrue( $checkbox->isChecked() );

		// Can pass a falsey value
		$checkbox = Checkbox::make( 'test' )->checked( false );
		self::assertFalse( $checkbox->isChecked() );

		$checkbox = Checkbox::make( 'test' )->checked( 0 );
		self::assertFalse( $checkbox->isChecked() );

		// Can pass a callable
		$checkbox = Checkbox::make( 'test' )->checked( function () {
			return true;
		} );
		self::assertTrue( $checkbox->isChecked() );
	}
}
