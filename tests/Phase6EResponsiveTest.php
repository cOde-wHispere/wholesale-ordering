<?php

namespace WholesaleOrdering\Tests;

use PHPUnit\Framework\TestCase;

defined( 'ABSPATH' ) || exit;

/**
 * Phase 6E responsive CSS contract tests.
 *
 * These tests do not replace manual browser QA. They verify that the responsive
 * stylesheet contains the structural protections required by the Phase 6E QA
 * plan.
 */
final class Phase6EResponsiveTest extends TestCase {

	/**
	 * @return string
	 */
	private function get_stylesheet(): string {
		$stylesheet = dirname( __DIR__ ) . '/assets/css/frontend.css';

		$this->assertFileExists( $stylesheet );

		$contents = file_get_contents( $stylesheet );

		$this->assertIsString( $contents );

		return $contents;
	}

	public function test_stylesheet_contains_required_responsive_breakpoints(): void {
		$css = $this->get_stylesheet();

		$this->assertStringContainsString(
			'@media (max-width: 767px)',
			$css
		);

		$this->assertStringContainsString(
			'@media (min-width: 768px) and (max-width: 1023px)',
			$css
		);

		$this->assertStringContainsString(
			'@media (min-width: 1024px)',
			$css
		);
	}

	public function test_mobile_layout_stacks_catalogue_controls(): void {
		$css = $this->get_stylesheet();

		$this->assertStringContainsString(
			'.wholesale-ordering-search-row',
			$css
		);

		$this->assertStringContainsString(
			'.wholesale-ordering-filter-grid',
			$css
		);

		$this->assertStringContainsString(
			'grid-template-columns: 1fr;',
			$css
		);

		$this->assertStringContainsString(
			'flex-direction: column;',
			$css
		);
	}

	public function test_stylesheet_contains_horizontal_overflow_protection(): void {
		$css = $this->get_stylesheet();

		$this->assertStringContainsString(
			'max-width: 100%;',
			$css
		);

		$this->assertStringContainsString(
			'overflow-x: auto;',
			$css
		);

		$this->assertStringContainsString(
			'overflow-wrap: anywhere;',
			$css
		);
	}

	public function test_interactive_controls_have_touch_sizing_and_focus_state(): void {
		$css = $this->get_stylesheet();

		$this->assertStringContainsString(
			'min-height: 2.75rem;',
			$css
		);

		$this->assertStringContainsString(
			':focus-visible',
			$css
		);
	}

	public function test_responsive_styles_support_reduced_motion(): void {
		$css = $this->get_stylesheet();

		$this->assertStringContainsString(
			'@media (prefers-reduced-motion: reduce)',
			$css
		);
	}
}
