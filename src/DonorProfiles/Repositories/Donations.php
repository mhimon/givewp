<?php
namespace Give\DonorProfiles\Repositories;

use Give\ValueObjects\Money;
use Give\Framework\Database\DB;
use InvalidArgumentException;

/**
 * @since 2.10.0
 */
class Donations {
	/**
	 * Get donations count for donor
	 *
	 * @param int $donorId
	 * @since 2.10.0
	 *
	 * @return int
	 */
	public function getDonationCount( $donorId ) {
		$aggregate = $this->getDonationAggregate( 'count(revenue.id)', $donorId );
		return $aggregate->result;
	}

	/**
	 * Get donor revenue
	 *
	 * @param int $donorId
	 * @since 2.10.0
	 *
	 * @return string
	 */
	public function getRevenue( $donorId ) {
		$aggregate = $this->getDonationAggregate( 'sum(revenue.amount)', $donorId );
		error_log( serialize( $aggregate ) );
		return Money::ofMinor( $aggregate->result, give_get_option( 'currency' ) )->getAmount();
	}

	/**
	 * Get average donor revenue
	 *
	 * @param int $donorId
	 * @since 2.10.0
	 *
	 * @return string
	 */
	public function getAverageRevenue( $donorId ) {
		;
		$aggregate = $this->getDonationAggregate( 'avg(revenue.amount)', $donorId );
		return Money::ofMinor( $aggregate->result, give_get_option( 'currency' ) )->getAmount();
	}

	private function getDonationAggregate( $rawAggregate, $donorId ) {
		global $wpdb;
		return DB::get_row(
			DB::prepare(
				"
				SELECT {$rawAggregate} as result
				FROM {$wpdb->give_revenue} as revenue
					INNER JOIN {$wpdb->posts} as posts ON revenue.donation_id = posts.ID
					INNER JOIN {$wpdb->prefix}give_donationmeta as donationmeta ON revenue.donation_id = donationmeta.donation_id
				WHERE donationmeta.meta_key = '_give_payment_donor_id'
					AND donationmeta.meta_value = {$donorId}
					AND posts.post_status IN ( 'publish', 'give_subscription' )
			"
			)
		);
	}

	/**
	 * Get all donation ids by donor ID
	 *
	 * @param int $donorId
	 * @since 2.10.0
	 * @return array Donation IDs
	 */
	protected function getDonationIDs( $donorId ) {
		global $wpdb;

		$result = $wpdb->get_results(
			$wpdb->prepare(
				"
                SELECT 
                    donation_id as id
                FROM 
                    {$wpdb->give_revenue} as revenue
                INNER JOIN 
                    {$wpdb->posts} as posts ON revenue.donation_id = posts.ID
                WHERE 
                    posts.post_author = %d
                AND 
                    posts.post_status IN ( 'publish', 'give_subscription' )
				",
				$donorId
			)
		);

		$ids = [];
		if ( $result ) {
			foreach ( $result as $donation ) {
				$ids[] = $donation->id;
			}
		}

		return $ids;
	}



	/**
	 * Get all donations by donor ID
	 *
	 * @param int $donorId
	 * @since 2.10.0
	 * @return array Ddonations
	 */
	public function getDonations( $donorId ) {

		$ids = $this->getDonationIds( $donorId );

		$args = [
			'number'   => -1,
			'post__in' => $ids,
		];

		$query    = new \Give_Payments_Query( $args );
		$payments = $query->get_payments();

		$data = [];
		foreach ( $payments as $payment ) {
			$data[ $payment->ID ] = [
				'form'    => $this->getFormInfo( $payment ),
				'payment' => $this->getPaymentInfo( $payment ),
				'donor'   => $this->getDonorInfo( $payment ),
			];
		}
		return $data;
	}

	/**
	 * Get form info
	 *
	 * @param Give_Payment $payment
	 * @since 2.10.0
	 * @return array Payment form info
	 */
	protected function getFormInfo( $payment ) {
		return [
			'title' => $payment->form_title,
			'id'    => $payment->form_id,
		];
	}

	/**
	 * Get payment info
	 *
	 * @param Give_Payment $payment
	 * @since 2.10.0
	 * @return array Payment info
	 */
	protected function getPaymentInfo( $payment ) {

		$gateways = give_get_payment_gateways();

		return [
			'amount'   => $this->getFormattedAmount( $payment->subtotal, $payment ),
			'currency' => $payment->currency,
			'fee'      => $this->getFormattedAmount( ( $payment->total - $payment->subtotal ), $payment ),
			'total'    => $this->getFormattedAmount( $payment->total, $payment ),
			'method'   => isset( $gateways[ $payment->gateway ]['checkout_label'] ) ? $gateways[ $payment->gateway ]['checkout_label'] : '',
			'status'   => $this->getFormattedStatus( $payment->status ),
			'date'     => date_i18n( give_date_format( 'checkout' ), strtotime( $payment->date ) ),
			'time'     => date_i18n( 'g:i a', strtotime( $payment->date ) ),
			'mode'     => $payment->get_meta( '_give_payment_mode' ),
		];
	}

	/**
	 * Get formatted status object (used for rendering status correctly in Donor Profile)
	 *
	 * @param string $status
	 * @since 2.10.0
	 * @return array Formatted status object (with color and label)
	 */
	protected function getFormattedStatus( $status ) {
		$statusMap = [
			'publish' => [
				'color' => '#7AD03A',
				'label' => esc_html__( 'Complete', 'give' ),
			],
		];

		return isset( $statusMap[ $status ] ) ? $statusMap[ $status ] : [
			'color' => '#FFBA00',
			'label' => esc_html__( 'Unknown', 'give' ),
		];
	}

	/**
	 * Get formatted payment amount
	 *
	 * @param float $amount
	 * @param Give_Payment $payment
	 * @since 2.10.0
	 * @return string Formatted payment amount (with correct decimals and currency symbol)
	 */
	protected function getformattedAmount( $amount, $payment ) {
		return give_currency_filter(
			give_format_amount(
				$amount,
				[
					'donation_id' => $payment->ID,
				]
			),
			[
				'currency_code'   => $payment->currency,
				'decode_currency' => true,
				'sanitize'        => false,
			]
		);
	}

	/**
	 * Get donor info
	 *
	 * @param Give_Payment $payment
	 * @since 2.10.0
	 * @return array Donor info
	 */
	protected function getDonorInfo( $payment ) {
		return $payment->user_info;
	}
}
