<?php
/**
 * Plugin Name: Bridgit Page Publisher
 * Description: Safely serves selected Bridgit marketing pages from the approved Cloudflare Pages production site while WordPress continues to handle the blog, admin, media and all other routes.
 * Version: 1.5.1
 * Author: Bridgit Care
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Bridgit_Page_Publisher {
	const OPTION_KEY      = 'bridgit_page_publisher_settings';
	const VERSION_KEY     = 'bridgit_page_publisher_version';
	const VERSION         = '1.5.1';
	const LEAD_SECRET_HASH = '46b6cec816ae887734da76546e6e958c74508397b87b30d02525ab9d1007d0ec';
	const CACHE_KEYS_KEY  = 'bridgit_page_publisher_cache_keys';
	const ORIGIN          = 'https://bridgit-main-site.pages.dev';
	const CACHE_FRESH_FOR = 300;
	const CACHE_KEEP_FOR  = DAY_IN_SECONDS;

	private static $managed_request = false;

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'add_settings_page' ) );
		add_action( 'admin_init', array( __CLASS__, 'maybe_upgrade' ), 5 );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_post_bridgit_publisher_purge', array( __CLASS__, 'handle_cache_purge' ) );
		add_action( 'rest_api_init', array( __CLASS__, 'register_api_endpoints' ) );
		add_action( 'template_redirect', array( __CLASS__, 'maybe_serve_managed_page' ), 0 );
		add_filter( 'body_class', array( __CLASS__, 'add_blog_body_class' ) );
		add_action( 'wp_enqueue_scripts', array( __CLASS__, 'enqueue_blog_shell' ), 100 );
		add_action( 'wp_body_open', array( __CLASS__, 'render_blog_header' ), 1 );
		add_action( 'wp_footer', array( __CLASS__, 'render_blog_footer' ), 1 );
	}

	public static function activate() {
		if ( false === get_option( self::OPTION_KEY, false ) ) {
			add_option(
				self::OPTION_KEY,
				array(
					'enabled'    => 0,
					'brand_blog' => 1,
					'routes'     => implode( "\n", self::default_routes() ),
				),
				'',
				false
			);
		}

		self::maybe_upgrade();
	}

	public static function maybe_upgrade() {
		$installed_version = get_option( self::VERSION_KEY, '0' );

		if ( version_compare( $installed_version, self::VERSION, '>=' ) ) {
			return;
		}

		$settings = get_option( self::OPTION_KEY, array() );
		if ( is_array( $settings ) && isset( $settings['routes'] ) ) {
			$routes = preg_split( '/\R/', (string) $settings['routes'] );
			$routes = array_values( array_filter( array_map( array( __CLASS__, 'normalize_path' ), $routes ) ) );

			$required_routes = array( '/leadership-team', '/responsible-ai', '/social-investors', '/our-impact', '/tools', '/sandy', '/commissioners-digital-toolkit', '/digital-readiness-review', '/demand-capacity-planner', '/pathway-mapper', '/responsible-ai-action-plan', '/partnership-builder', '/social-impact-advisor' );
			foreach ( $required_routes as $required_route ) {
				if ( ! in_array( $required_route, $routes, true ) ) {
					$routes[] = $required_route;
				}
			}
			$settings['routes'] = implode( "\n", $routes );
			update_option( self::OPTION_KEY, $settings, false );

			if ( ! isset( $settings['brand_blog'] ) ) {
				$settings['brand_blog'] = 1;
				update_option( self::OPTION_KEY, $settings, false );
			}
		}

		update_option( self::VERSION_KEY, self::VERSION, false );
		self::purge_cache();
	}

	public static function register_api_endpoints() {
		register_rest_route(
			'bridgit/v1',
			'/lead',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'receive_lead' ),
				'permission_callback' => array( __CLASS__, 'authenticate_lead_request' ),
			)
		);

		register_rest_route(
			'bridgit/v1',
			'/toolkit-report',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( __CLASS__, 'send_toolkit_report' ),
				'permission_callback' => array( __CLASS__, 'authenticate_lead_request' ),
			)
		);
	}

	public static function authenticate_lead_request( WP_REST_Request $request ) {
		$provided = trim( (string) $request->get_header( 'x-bridgit-lead-secret' ) );
		$provided_hash = hash( 'sha256', $provided );

		if ( '' === $provided || ! hash_equals( self::LEAD_SECRET_HASH, $provided_hash ) ) {
			return new WP_Error( 'bridgit_invalid_lead_secret', 'Invalid lead webhook credentials.', array( 'status' => 401 ) );
		}

		return true;
	}

	public static function receive_lead( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		$data = is_array( $data ) ? $data : array();

		if ( empty( $data['consent'] ) ) {
			return new WP_Error( 'bridgit_lead_consent_required', 'Explicit consent is required before a lead can be sent.', array( 'status' => 400 ) );
		}

		$name         = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		$email        = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
		$organisation = isset( $data['organisation'] ) ? sanitize_text_field( $data['organisation'] ) : '';
		$role         = isset( $data['role'] ) ? sanitize_text_field( $data['role'] ) : '';
		$phone        = isset( $data['phone'] ) ? sanitize_text_field( $data['phone'] ) : '';
		$interest     = isset( $data['interest'] ) ? sanitize_text_field( $data['interest'] ) : '';
		$source_page  = isset( $data['source_page'] ) ? sanitize_key( $data['source_page'] ) : 'website';
		$summary      = isset( $data['summary'] ) ? sanitize_textarea_field( $data['summary'] ) : '';

		if ( '' === $name || ! is_email( $email ) || '' === $organisation || '' === $interest ) {
			return new WP_Error( 'bridgit_invalid_lead', 'Name, valid email, organisation and area of interest are required.', array( 'status' => 400 ) );
		}

		$rate_key = 'bridgit_lead_' . md5( strtolower( $email ) );
		if ( get_transient( $rate_key ) ) {
			return rest_ensure_response( array( 'success' => true, 'message' => 'This enquiry has already been sent recently.' ) );
		}

		$subject = sprintf( 'New Bridgit website lead: %s', $organisation );
		$sent    = wp_mail(
			'contact@bridgit.care',
			$subject,
			self::render_lead_email( $name, $email, $organisation, $role, $phone, $interest, $source_page, $summary ),
			self::html_mail_headers( $name . ' <' . $email . '>' )
		);

		if ( ! $sent ) {
			return new WP_Error( 'bridgit_lead_email_failed', 'The enquiry could not be sent just now.', array( 'status' => 500 ) );
		}

		set_transient( $rate_key, 1, 10 * MINUTE_IN_SECONDS );

		return rest_ensure_response( array( 'success' => true, 'message' => 'Your details have been sent to the Bridgit team.' ) );
	}

	public static function send_toolkit_report( WP_REST_Request $request ) {
		$data = $request->get_json_params();
		$data = is_array( $data ) ? $data : array();

		if ( empty( $data['consent'] ) ) {
			return new WP_Error( 'bridgit_report_consent_required', 'Explicit consent is required before a report can be emailed.', array( 'status' => 400 ) );
		}

		$name         = isset( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
		$email        = isset( $data['email'] ) ? sanitize_email( $data['email'] ) : '';
		$organisation = isset( $data['organisation'] ) ? sanitize_text_field( $data['organisation'] ) : '';
		$toolkit_type = isset( $data['toolkit_type'] ) ? sanitize_key( $data['toolkit_type'] ) : '';
		$context      = isset( $data['context'] ) ? sanitize_textarea_field( $data['context'] ) : '';
		$recommendation = isset( $data['recommendation'] ) ? sanitize_textarea_field( $data['recommendation'] ) : '';
		$actions      = isset( $data['actions'] ) ? sanitize_textarea_field( $data['actions'] ) : '';
		$considerations = isset( $data['considerations'] ) ? sanitize_textarea_field( $data['considerations'] ) : '';
		$options      = isset( $data['options'] ) ? sanitize_textarea_field( $data['options'] ) : '';

		$allowed_types = array( 'co-production', 'digital-commissioning', 'digital-readiness', 'demand-capacity', 'pathway-mapper', 'responsible-ai', 'partnership-builder', 'social-impact' );
		if ( '' === $name || ! is_email( $email ) || ! in_array( $toolkit_type, $allowed_types, true ) || '' === $recommendation || '' === $actions ) {
			return new WP_Error( 'bridgit_invalid_report', 'Name, valid email, toolkit type, recommendation and actions are required.', array( 'status' => 400 ) );
		}

		$rate_key = 'bridgit_report_' . md5( strtolower( $email ) . '|' . $toolkit_type );
		if ( get_transient( $rate_key ) ) {
			return rest_ensure_response( array( 'success' => true, 'message' => 'This report has already been emailed recently.' ) );
		}

		$report = self::report_definition( $toolkit_type );
		$sent   = wp_mail(
			$email,
			$report['title'],
			self::render_report_email( $name, $organisation, $report, $context, $recommendation, $actions, $considerations, $options ),
			self::html_mail_headers()
		);

		if ( ! $sent ) {
			return new WP_Error( 'bridgit_report_email_failed', 'The report could not be emailed just now.', array( 'status' => 500 ) );
		}

		set_transient( $rate_key, 1, 30 * MINUTE_IN_SECONDS );
		return rest_ensure_response( array( 'success' => true, 'message' => 'Your report has been emailed to you.' ) );
	}

	private static function html_mail_headers( $reply_to = '' ) {
		$headers = array(
			'Content-Type: text/html; charset=UTF-8',
			'From: Bridgit <no-reply@bridgit.care>',
		);

		$headers[] = 'Reply-To: ' . ( '' !== $reply_to ? $reply_to : 'Bridgit Care <contact@bridgit.care>' );

		return $headers;
	}

	private static function report_definition( $type ) {
		$definitions = array(
			'co-production' => array(
				'title' => 'Your co-production plan from Sandy',
				'coach' => 'Sandy, Bridgit’s Co-production Coach',
				'label' => 'Co-production plan',
				'options_label' => 'Engagement options',
				'boundary' => 'Keep this plan live: return to it with the people involved, record what changes, and close the feedback loop.',
				'resources' => array(
					array( 'SCIE co-production', 'https://www.scie.org.uk/co-production/' ),
					array( 'Think Local Act Personal co-production hub', 'https://thinklocalactpersonal.org.uk/our-hubs/co-production/' ),
					array( 'NHS England: Working with people and communities', 'https://www.england.nhs.uk/get-involved/resources/working-in-partnership-with-people-and-communities-statutory-guidance/' ),
				),
			),
			'digital-commissioning' => array(
				'title' => 'Your digital commissioning recommendation report',
				'coach' => 'Bridgit’s Digital Commissioning Guide',
				'label' => 'Recommended route',
				'options_label' => 'Solution and supplier options to explore',
				'boundary' => 'This is decision support, not legal or procurement advice. Check your organisation’s governance and commercial requirements before proceeding.',
				'resources' => array(
					array( 'AI Playbook for the UK Government', 'https://www.gov.uk/government/publications/ai-playbook-for-the-uk-government' ),
					array( 'Digital, Data and Technology Playbook', 'https://www.gov.uk/government/publications/the-digital-data-and-technology-playbook' ),
					array( 'Technology Code of Practice', 'https://www.gov.uk/guidance/the-technology-code-of-practice' ),
					array( 'ICO AI and data protection guidance', 'https://ico.org.uk/for-organisations/uk-gdpr-guidance-and-resources/artificial-intelligence/' ),
				),
			),
			'digital-readiness' => array( 'title' => 'Your digital readiness review', 'coach' => 'Bridgit’s Digital Readiness Guide', 'label' => 'Readiness recommendation', 'options_label' => 'Practical options to explore', 'boundary' => 'Use this as a working draft and involve the people affected before making a commitment.', 'resources' => array( array( 'GOV.UK Service Standard', 'https://www.gov.uk/service-manual/service-standard' ), array( 'Technology Code of Practice', 'https://www.gov.uk/guidance/the-technology-code-of-practice' ) ) ),
			'demand-capacity' => array( 'title' => 'Your demand and capacity action plan', 'coach' => 'Bridgit’s Demand and Capacity Planner', 'label' => 'Priority response', 'options_label' => 'Measures and service changes to test', 'boundary' => 'Use the plan to support local judgement. It does not replace operational, clinical or safeguarding decision-making.', 'resources' => array( array( 'NHS England: Demand and capacity', 'https://www.england.nhs.uk/long-read/demand-and-capacity/' ), array( 'LGA: Adult social care resources', 'https://www.local.gov.uk/our-support/our-improvement-offer/care-and-health-improvement/adult-social-care' ) ) ),
			'pathway-mapper' => array( 'title' => 'Your pathway improvement plan', 'coach' => 'Bridgit’s Pathway Mapper', 'label' => 'Pathway recommendation', 'options_label' => 'Partners and pathway actions', 'boundary' => 'Test the plan with people who use and deliver the service. Do not include confidential case information in this report.', 'resources' => array( array( 'GOV.UK Service Standard', 'https://www.gov.uk/service-manual/service-standard' ), array( 'SCIE co-production', 'https://www.scie.org.uk/co-production/' ) ) ),
			'responsible-ai' => array( 'title' => 'Your responsible AI action plan', 'coach' => 'Bridgit’s Responsible AI Guide', 'label' => 'Responsible AI recommendation', 'options_label' => 'Governance and delivery actions', 'boundary' => 'This is practical decision support, not legal, data-protection or information-security advice. Complete your own governance checks before implementation.', 'resources' => array( array( 'UK Government AI Playbook', 'https://www.gov.uk/government/publications/ai-playbook-for-the-uk-government' ), array( 'Data Ethics Framework', 'https://www.gov.uk/government/publications/data-ethics-framework/data-and-ai-ethics-framework' ), array( 'ICO AI guidance', 'https://ico.org.uk/for-organisations/uk-gdpr-guidance-and-resources/artificial-intelligence/' ) ) ),
			'partnership-builder' => array( 'title' => 'Your partnership growth plan', 'coach' => 'Bridgit’s Partnership Builder', 'label' => 'Partnership recommendation', 'options_label' => 'Partners and next conversations', 'boundary' => 'Use this as a starting point. Agree roles, consent, data sharing and governance with partners before launch.', 'resources' => array( array( 'Social Enterprise UK', 'https://www.socialenterprise.org.uk/' ), array( 'NCVO: Working in partnership', 'https://www.ncvo.org.uk/help-and-guidance/strategy-and-impact/working-in-partnership/' ) ) ),
			'social-impact' => array( 'title' => 'Your social impact action plan', 'coach' => 'Bridgit’s Social Impact Advisor, delivered in partnership with SEUK', 'label' => 'Impact plan', 'options_label' => 'Measures and evidence to build next', 'boundary' => 'This plan supports impact management. It is not a verified impact account, assurance statement or financial valuation.', 'resources' => array( array( 'Social Enterprise UK impact reports', 'https://www.socialenterprise.org.uk/social-enterprise-uk-impact-reports/' ), array( 'Social Value International principles', 'https://www.socialvalueint.org/principles' ), array( 'UK Government Social Value Model', 'https://www.gov.uk/government/publications/ppn-002-taking-account-of-social-value-in-the-award-of-contracts/ppn-002-taking-account-of-social-value-in-the-award-of-contracts-html' ) ) ),
		);

		return isset( $definitions[ $type ] ) ? $definitions[ $type ] : $definitions['digital-commissioning'];
	}

	private static function email_text( $text, $limit ) {
		$text = wp_html_excerpt( wp_strip_all_tags( (string) $text ), $limit, '…' );
		$text = str_replace( array( "\r\n", "\r" ), "\n", $text );

		/*
		 * Agents sometimes return a numbered or bulleted list as one paragraph.
		 * Insert a line break before each later marker so email clients can render
		 * every action as a distinct, readable list item.
		 */
		$text = preg_replace( '/[ \t]+(?=(?:\d{1,2}[\.)]|[•\-*])\s+)/u', "\n", $text );
		$lines = preg_split( '/\n+/', trim( $text ) );
		$html = '';
		$open_list = '';

		foreach ( $lines as $line ) {
			$line = trim( $line );
			if ( '' === $line ) {
				continue;
			}

			$list_type = '';
			$item_text = '';
			if ( preg_match( '/^\d{1,2}[\.)]\s+(.+)$/u', $line, $matches ) ) {
				$list_type = 'ol';
				$item_text = $matches[1];
			} elseif ( preg_match( '/^[•\-*]\s+(.+)$/u', $line, $matches ) ) {
				$list_type = 'ul';
				$item_text = $matches[1];
			}

			if ( $list_type ) {
				if ( $open_list && $open_list !== $list_type ) {
					$html .= '</' . $open_list . '>';
					$open_list = '';
				}
				if ( ! $open_list ) {
					$open_list = $list_type;
					$html .= '<' . $open_list . ' style="margin:4px 0 0;padding-left:24px;">';
				}
				$html .= '<li style="margin:0 0 10px;padding-left:3px;">' . esc_html( $item_text ) . '</li>';
				continue;
			}

			if ( $open_list ) {
				$html .= '</' . $open_list . '>';
				$open_list = '';
			}
			$html .= '<p style="margin:0 0 10px;">' . esc_html( $line ) . '</p>';
		}

		if ( $open_list ) {
			$html .= '</' . $open_list . '>';
		}

		return $html;
	}

	private static function email_section( $heading, $content ) {
		if ( '' === trim( (string) $content ) ) {
			return '';
		}

		return '<tr><td style="padding:0 32px 22px;"><div style="border:1px solid #e8dff0;border-radius:16px;background:#ffffff;padding:21px 22px;"><h2 style="margin:0 0 10px;color:#3c1269;font:800 18px/1.25 Arial,sans-serif;">' . esc_html( $heading ) . '</h2><div style="color:#4f586b;font:400 15px/1.65 Arial,sans-serif;">' . self::email_text( $content, 4000 ) . '</div></div></td></tr>';
	}

	private static function render_report_email( $name, $organisation, $report, $context, $recommendation, $actions, $considerations, $options ) {
		$resources = '';
		foreach ( $report['resources'] as $resource ) {
			$resources .= '<li style="margin:0 0 9px;"><a style="color:#6424ba;font-weight:700;text-decoration:underline;" href="' . esc_url( $resource[1] ) . '">' . esc_html( $resource[0] ) . '</a></li>';
		}

		$organisation_line = $organisation ? '<p style="margin:6px 0 0;color:#e9dbf7;font:400 14px/1.5 Arial,sans-serif;">Prepared for ' . esc_html( $organisation ) . '</p>' : '';

		return '<!doctype html><html lang="en"><body style="margin:0;padding:0;background:#f6f3fb;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0" style="background:#f6f3fb;"><tr><td align="center" style="padding:32px 14px;"><table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:640px;overflow:hidden;border-radius:24px;background:#ffffff;box-shadow:0 16px 42px rgba(45,20,75,.15);"><tr><td style="padding:32px;background:linear-gradient(112deg,#321062 0%,#6424ba 58%,#c932ae 100%);"><p style="margin:0 0 18px;color:#ffffff;font:900 26px/1 Arial,sans-serif;letter-spacing:-.8px;">bridgit<span style="color:#69c9e6;">.</span></p><p style="margin:0;color:#f5eefd;font:800 12px/1.3 Arial,sans-serif;letter-spacing:1.5px;text-transform:uppercase;">' . esc_html( $report['label'] ) . '</p><h1 style="margin:10px 0 0;color:#ffffff;font:800 31px/1.12 Arial,sans-serif;">Hello ' . esc_html( $name ) . ', here is your plan.</h1>' . $organisation_line . '</td></tr><tr><td style="padding:28px 32px 6px;color:#4f586b;font:400 16px/1.65 Arial,sans-serif;"><p style="margin:0;">Thank you for talking with <strong style="color:#261036;">' . esc_html( $report['coach'] ) . '</strong>. This is a practical working draft you can bring into your next conversation.</p></td></tr>' . self::email_section( 'Your context', $context ) . self::email_section( $report['label'], $recommendation ) . self::email_section( 'Practical next steps', $actions ) . self::email_section( 'Access, safety and delivery considerations', $considerations ) . self::email_section( $report['options_label'], $options ) . '<tr><td style="padding:0 32px 25px;"><div style="padding:20px 22px;border-radius:16px;background:#f5f0fa;"><h2 style="margin:0 0 10px;color:#3c1269;font:800 18px/1.25 Arial,sans-serif;">Trusted resources</h2><ul style="margin:0;padding-left:20px;color:#4f586b;font:400 15px/1.55 Arial,sans-serif;">' . $resources . '</ul></div></td></tr><tr><td style="padding:0 32px 30px;"><p style="margin:0;padding:17px 18px;border-left:4px solid #c932ae;background:#fff8fd;color:#5d5264;font:400 13px/1.55 Arial,sans-serif;">' . esc_html( $report['boundary'] ) . '</p></td></tr><tr><td align="center" style="padding:0 32px 34px;"><a href="https://bridgit.care/#book-a-call" style="display:inline-block;padding:14px 24px;border-radius:999px;background:linear-gradient(105deg,#4f8fc0,#a329b8);color:#ffffff;font:800 15px/1 Arial,sans-serif;text-decoration:none;">Talk through your next step</a></td></tr><tr><td style="padding:23px 32px;background:#1a1028;color:#d8cfe3;text-align:center;font:400 12px/1.6 Arial,sans-serif;">Bridgit Care &middot; <a style="color:#ffffff;" href="https://bridgit.care">bridgit.care</a> &middot; <a style="color:#ffffff;" href="mailto:contact@bridgit.care">contact@bridgit.care</a></td></tr></table></td></tr></table></body></html>';
	}

	private static function render_lead_email( $name, $email, $organisation, $role, $phone, $interest, $source_page, $summary ) {
		$fields = array(
			'Name' => $name,
			'Email' => $email,
			'Organisation' => $organisation,
			'Role' => $role ? $role : 'Not provided',
			'Phone' => $phone ? $phone : 'Not provided',
			'Area of interest' => $interest,
			'Source page' => $source_page,
		);
		$rows = '';
		foreach ( $fields as $label => $value ) {
			$rows .= '<tr><td style="padding:10px 0;border-bottom:1px solid #eee7f3;color:#746a7d;font:700 13px Arial,sans-serif;width:34%;">' . esc_html( $label ) . '</td><td style="padding:10px 0;border-bottom:1px solid #eee7f3;color:#271437;font:400 14px Arial,sans-serif;">' . esc_html( $value ) . '</td></tr>';
		}

		return '<!doctype html><html lang="en"><body style="margin:0;padding:0;background:#f6f3fb;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0"><tr><td align="center" style="padding:32px 14px;background:#f6f3fb;"><table role="presentation" width="640" cellspacing="0" cellpadding="0" border="0" style="width:100%;max-width:640px;overflow:hidden;border-radius:24px;background:#ffffff;box-shadow:0 16px 42px rgba(45,20,75,.15);"><tr><td style="padding:30px 32px;background:linear-gradient(112deg,#321062,#a329b8);"><p style="margin:0 0 10px;color:#ffffff;font:900 26px/1 Arial,sans-serif;">bridgit<span style="color:#69c9e6;">.</span></p><h1 style="margin:0;color:#ffffff;font:800 28px/1.2 Arial,sans-serif;">A new website enquiry</h1></td></tr><tr><td style="padding:30px 32px 10px;"><p style="margin:0;color:#4f586b;font:400 16px/1.6 Arial,sans-serif;">A visitor has asked to speak with the Bridgit team and consented to their details being shared.</p></td></tr><tr><td style="padding:14px 32px 24px;"><table role="presentation" width="100%" cellspacing="0" cellpadding="0" border="0">' . $rows . '</table></td></tr><tr><td style="padding:0 32px 32px;"><div style="padding:20px 22px;border-radius:16px;background:#f5f0fa;"><h2 style="margin:0 0 10px;color:#3c1269;font:800 18px/1.25 Arial,sans-serif;">Conversation summary</h2><div style="color:#4f586b;font:400 15px/1.65 Arial,sans-serif;">' . self::email_text( $summary ? $summary : 'No additional summary provided.', 1200 ) . '</div></div></td></tr><tr><td style="padding:20px 32px;background:#1a1028;color:#d8cfe3;text-align:center;font:400 12px/1.6 Arial,sans-serif;">Bridgit Care &middot; contact@bridgit.care</td></tr></table></td></tr></table></body></html>';
	}

	private static function is_blog_content_request() {
		return is_home()
			|| is_page( 'blog' )
			|| is_singular( 'post' )
			|| is_post_type_archive( 'post' )
			|| is_category()
			|| is_tag()
			|| is_author()
			|| is_date()
			|| is_search();
	}

	private static function information_page_slugs() {
		return array(
			'privacy-policy',
			'accessibility-statement',
			'intellectual-property',
			'terms-conditions',
			'contact-us',
			'developed-with-carers',
		);
	}

	private static function is_branded_shell_request() {
		if ( is_admin() || wp_doing_ajax() || self::$managed_request ) {
			return false;
		}

		$settings = self::settings();
		if ( empty( $settings['brand_blog'] ) ) {
			return false;
		}

		return self::is_blog_content_request() || is_page( self::information_page_slugs() );
	}

	public static function add_blog_body_class( $classes ) {
		if ( self::is_branded_shell_request() ) {
			$classes[] = 'bpp-site-branded';
		}

		return $classes;
	}

	public static function enqueue_blog_shell() {
		if ( ! self::is_branded_shell_request() ) {
			return;
		}

		wp_register_style( 'bridgit-blog-shell', false, array(), self::VERSION );
		wp_enqueue_style( 'bridgit-blog-shell' );

		$font_url = esc_url_raw( self::ORIGIN . '/fonts/nunito-latin.woff2' );
		$css      = '@font-face{font-family:"Bridgit Nunito";font-style:normal;font-weight:400 900;font-display:swap;src:url("' . $font_url . '") format("woff2")}';
		$css     .= <<<'CSS'
body.bpp-site-branded #masthead,
body.bpp-site-branded #colophon,
body.bpp-site-branded .elementor-location-header,
body.bpp-site-branded .elementor-location-footer,
body.bpp-site-branded .hfe-before-footer-wrap{display:none!important}
body.bpp-site-branded{--bpp-ink:#172033;--bpp-muted:#576078;--bpp-purple:#6424ba;--bpp-purple-dark:#351066;--bpp-pink:#d63ab7;--bpp-mist:#f6f3fb;--bpp-shadow:0 22px 60px rgba(48,23,79,.14);overflow-x:hidden}
.bpp-site-header,.bpp-site-footer,.bpp-support-dialog{font-family:"Bridgit Nunito",Nunito,Arial,sans-serif;box-sizing:border-box}
.bpp-site-header *,.bpp-site-footer *,.bpp-support-dialog *{box-sizing:border-box}
.bpp-shell{width:min(1180px,calc(100% - 40px));margin-inline:auto}
.bpp-site-header{position:sticky;top:0;z-index:9990;background:rgba(255,255,255,.96);backdrop-filter:blur(16px);border-bottom:1px solid rgba(53,16,102,.08)}
.admin-bar .bpp-site-header{top:32px}
.bpp-header-inner{height:82px;display:flex;align-items:center;justify-content:space-between}
.bpp-brand{display:inline-flex;align-items:center;text-decoration:none!important}
.bpp-brand-logo{display:block;width:154px;height:auto}
.bpp-site-nav{display:flex;align-items:center;gap:28px}
.bpp-site-nav>a,.bpp-site-nav summary,.bpp-nav-support{color:var(--bpp-ink)!important;text-decoration:none!important;font-size:15px;font-weight:800;line-height:1.4;cursor:pointer}
.bpp-site-nav>a:hover,.bpp-site-nav summary:hover,.bpp-nav-support:hover{color:var(--bpp-purple)!important}
.bpp-site-nav .bpp-active{color:var(--bpp-purple)!important}
.bpp-site-nav details{position:relative;margin:0}
.bpp-site-nav summary{list-style:none}
.bpp-site-nav summary::-webkit-details-marker{display:none}
.bpp-site-nav summary:after{content:"⌄";margin-left:6px}
.bpp-nav-panel{position:absolute;top:36px;left:-20px;display:grid;width:220px;padding:12px;background:#fff;border:1px solid #ebe3f4;border-radius:16px;box-shadow:var(--bpp-shadow)}
.bpp-nav-panel a{display:block;padding:8px 10px;border-radius:8px;color:var(--bpp-ink)!important;text-decoration:none!important;font-size:14px;font-weight:800}
.bpp-nav-panel a:hover{background:var(--bpp-mist);color:var(--bpp-purple)!important}
.bpp-nav-support{border:0;background:none;padding:0;font-family:inherit}
.bpp-button{display:inline-flex;align-items:center;justify-content:center;padding:10px 18px;border-radius:999px;background:linear-gradient(104deg,#4f8fc0 12%,#a329b8 75%);color:#fff!important;text-decoration:none!important;font-size:15px;font-weight:900;box-shadow:0 12px 28px rgba(100,36,186,.22);transition:transform .2s ease,box-shadow .2s ease}
.bpp-button:hover{color:#fff!important;transform:translateY(-2px);box-shadow:0 16px 34px rgba(100,36,186,.3)}
.bpp-site-header a.bpp-button,.bpp-site-header a.bpp-button:visited,.bpp-site-header a.bpp-button:hover,.bpp-site-header a.bpp-button:focus{color:#fff!important}
.bpp-menu-button{display:none;border:0;background:none;padding:8px;cursor:pointer}
.bpp-menu-button span:not(.bpp-sr-only){display:block;width:24px;height:2px;margin:5px;background:var(--bpp-ink)}
.bpp-sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.bpp-site-footer{position:relative;z-index:2;padding:70px 0 25px;background:#171024;color:#dfd8e8;font-size:16px;line-height:1.6}
.bpp-footer-grid{display:grid;grid-template-columns:1.25fr .9fr .9fr .9fr;gap:48px}
.bpp-footer-logo{display:block;width:170px;height:auto;margin-bottom:20px}
.bpp-site-footer p{margin:0 0 14px;color:#dfd8e8}
.bpp-site-footer h2{margin:0 0 14px;color:#fff;font-size:18px;line-height:1.2}
.bpp-site-footer a{display:block;margin:8px 0;color:#dfd8e8!important;text-decoration:underline;text-decoration-color:transparent;text-underline-offset:4px}
.bpp-site-footer a:hover{color:#fff!important;text-decoration-color:currentColor}
.bpp-site-footer .bpp-brand{display:inline-flex;margin:0;text-decoration:none}
.bpp-footer-bottom{display:flex;justify-content:space-between;gap:30px;margin-top:50px;padding-top:22px;border-top:1px solid rgba(255,255,255,.12);font-size:13px;color:#a99eb5}
.bpp-support-dialog{width:min(1100px,calc(100% - 32px));max-height:min(90vh,900px);padding:0;border:0;border-radius:28px;background:#fff;color:var(--bpp-ink);box-shadow:0 32px 100px rgba(20,8,36,.35)}
.bpp-support-dialog::backdrop{background:rgba(23,16,36,.72);backdrop-filter:blur(6px)}
.bpp-support-inner{padding:28px}
.bpp-support-heading{display:flex;align-items:flex-start;justify-content:space-between;gap:28px;margin-bottom:22px}
.bpp-support-heading p{margin:10px 0 0;color:var(--bpp-muted);font-size:17px}
.bpp-support-heading .bpp-eyebrow{margin:0 0 8px;color:var(--bpp-purple);font-size:13px;font-weight:900;letter-spacing:.12em;text-transform:uppercase}
.bpp-support-heading h2{margin:0;color:var(--bpp-ink);font-size:clamp(32px,4vw,50px);line-height:1.08;letter-spacing:-.035em}
.bpp-support-close{flex:0 0 auto;display:grid;place-items:center;width:46px;height:46px;padding:0;border:1px solid #dfd5e8;border-radius:50%;background:#fff;color:var(--bpp-purple-dark);font:400 32px/1 Arial,sans-serif;cursor:pointer}
.bpp-support-close:hover{background:var(--bpp-mist)}
.bpp-support-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:14px}
.bpp-support-option{position:relative;overflow:hidden;height:185px;border-radius:18px;background:#ddd;color:#fff!important;text-decoration:none!important;box-shadow:0 9px 24px rgba(25,12,39,.12);transition:transform .2s ease,box-shadow .2s ease}
.bpp-support-option:hover{transform:translateY(-3px);box-shadow:0 14px 30px rgba(25,12,39,.2)}
.bpp-support-option:focus-visible{outline:4px solid #3aa7d6;outline-offset:3px}
.bpp-support-option img{display:block;width:100%;height:100%;object-fit:cover;transition:transform .3s ease}
.bpp-support-option:hover img{transform:scale(1.035)}
.bpp-support-option:after{content:"";position:absolute;inset:35% 0 0;background:linear-gradient(transparent,rgba(19,8,30,.9))}
.bpp-support-copy{position:absolute;z-index:1;inset:auto 18px 16px;display:flex;flex-direction:column;color:#fff}
.bpp-support-copy strong{font-size:23px;line-height:1.1}
.bpp-support-copy small{margin-top:5px;color:#f2edf7;font-size:13px;line-height:1.35}
@media(max-width:900px){.bpp-site-nav{display:none;position:absolute;top:72px;left:20px;right:20px;align-items:stretch;flex-direction:column;gap:8px;padding:20px;border-radius:18px;background:#fff;box-shadow:var(--bpp-shadow)}.bpp-site-nav.bpp-is-open{display:flex}.bpp-site-nav>a,.bpp-site-nav summary,.bpp-nav-support{display:block;width:100%;padding:8px;text-align:left}.bpp-nav-panel{position:static;display:block;width:100%;margin-top:8px;box-shadow:none}.bpp-menu-button{display:block}.bpp-footer-grid{grid-template-columns:1fr 1fr}.bpp-footer-grid>div:first-child{grid-column:1/-1}.bpp-support-grid{grid-template-columns:repeat(2,1fr)}}
@media(max-width:782px){.admin-bar .bpp-site-header{top:46px}}
@media(max-width:600px){.bpp-shell{width:min(100% - 28px,1180px)}.bpp-header-inner{height:70px}.bpp-footer-grid{grid-template-columns:1fr}.bpp-footer-grid>div:first-child{grid-column:auto}.bpp-footer-bottom{flex-direction:column}.bpp-support-dialog{width:min(100% - 16px,1100px);border-radius:20px}.bpp-support-inner{padding:22px 16px}.bpp-support-heading{gap:14px}.bpp-support-close{width:40px;height:40px}.bpp-support-grid{grid-template-columns:1fr}.bpp-support-option{height:180px}.bpp-support-copy strong{font-size:21px}}
@media(prefers-reduced-motion:reduce){.bpp-site-header *,.bpp-site-footer *,.bpp-support-dialog *{scroll-behavior:auto!important;animation-duration:.01ms!important;transition-duration:.01ms!important}}
CSS;

		wp_add_inline_style( 'bridgit-blog-shell', $css );
	}

	private static function solution_links() {
		return array(
			'Carer services'      => '/carer-services',
			'Council Front Door'  => '/local-authorities',
			'Care leavers'        => '/care-leavers',
			'Healthy ageing'      => '/healthy-ageing',
			'Social enterprises'  => '/social-enterprises',
			'NHS partners'        => '/nhs',
			'Corporate partners'  => '/corporate-partners',
		);
	}

	private static function support_links() {
		return array(
			array( 'Adult carers', 'Support when you care for another adult', 'https://carers.bridgit.care/', 'adult-carers.webp' ),
			array( 'Young carers', 'Help for young people with caring roles', 'https://young.bridgit.care/', 'young-carers.webp' ),
			array( 'Employers', 'Support for employees and workplaces', 'https://employers.bridgit.care/', 'employers.webp' ),
			array( 'Local support', 'Find trusted help near you', 'https://local.bridgit.care/', 'local-support.webp' ),
			array( 'Care leavers', 'Guidance for life after care', 'https://next.bridgit.care/', 'care-leavers.webp' ),
			array( 'Ageing well', 'Stay active, connected and independent', 'https://agewell.bridgit.care/', 'ageing-well.webp' ),
			array( 'Moving to the UK', 'Help settling into life and work in the UK', 'https://ai.myuk.life/', 'moving-to-uk.webp' ),
		);
	}

	public static function render_blog_header() {
		if ( ! self::is_branded_shell_request() ) {
			return;
		}

		$logo_url = self::ORIGIN . '/images/bridgit-care-logo.png';
		$is_blog  = self::is_blog_content_request();
		?>
		<header class="bpp-site-header" data-bpp-header>
			<div class="bpp-shell bpp-header-inner">
				<a class="bpp-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Bridgit Care home">
					<img class="bpp-brand-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="Bridgit Care" width="300" height="103">
				</a>
				<button class="bpp-menu-button" type="button" aria-expanded="false" aria-controls="bpp-site-menu" data-bpp-menu-button>
					<span class="bpp-sr-only"><?php esc_html_e( 'Open navigation', 'bridgit-page-publisher' ); ?></span>
					<span></span><span></span><span></span>
				</button>
				<nav class="bpp-site-nav" id="bpp-site-menu" aria-label="Main navigation" data-bpp-menu>
					<a href="<?php echo esc_url( home_url( '/#how-it-works' ) ); ?>">How it works</a>
					<details>
						<summary>Who we help</summary>
						<div class="bpp-nav-panel">
							<?php foreach ( self::solution_links() as $label => $path ) : ?>
								<a href="<?php echo esc_url( home_url( $path ) ); ?>"><?php echo esc_html( $label ); ?></a>
							<?php endforeach; ?>
						</div>
					</details>
					<a href="<?php echo esc_url( home_url( '/our-impact' ) ); ?>">Our impact</a>
					<a href="<?php echo esc_url( home_url( '/tools' ) ); ?>">Tools</a>
					<a href="<?php echo esc_url( home_url( '/leadership-team' ) ); ?>">Our team</a>
					<a<?php echo $is_blog ? ' class="bpp-active" aria-current="page"' : ''; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?> href="<?php echo esc_url( home_url( '/blog' ) ); ?>">Blog</a>
					<button class="bpp-nav-support" type="button" data-bpp-support-open aria-haspopup="dialog">Looking for support?</button>
					<a class="bpp-button" href="<?php echo esc_url( home_url( '/#book-a-call' ) ); ?>">Book a call</a>
				</nav>
			</div>
		</header>
		<?php
	}

	public static function render_blog_footer() {
		if ( ! self::is_branded_shell_request() ) {
			return;
		}

		$logo_url = self::ORIGIN . '/images/bridgit-care-logo.png';
		?>
		<footer class="bpp-site-footer">
			<div class="bpp-shell bpp-footer-grid">
				<div>
					<a class="bpp-brand" href="<?php echo esc_url( home_url( '/' ) ); ?>" aria-label="Bridgit Care home"><img class="bpp-footer-logo" src="<?php echo esc_url( $logo_url ); ?>" alt="Bridgit Care" width="300" height="103"></a>
					<p>AI-powered coaching that helps organisations extend support without losing the human connection.</p>
				</div>
				<div>
					<h2>Get in touch</h2>
					<a href="tel:+443455481654">+44 345 548 1654</a>
					<a href="mailto:contact@bridgit.care">contact@bridgit.care</a>
					<p>Ergo, Bridgehead Business Park, Hessle, HU13 0GD</p>
				</div>
				<div>
					<h2>Who we help</h2>
					<?php foreach ( self::solution_links() as $label => $path ) : ?>
						<a href="<?php echo esc_url( home_url( $path ) ); ?>"><?php echo esc_html( $label ); ?></a>
					<?php endforeach; ?>
					<a href="https://carers.bridgit.care/" data-bpp-support-open>Looking for support?</a>
				</div>
				<div>
					<h2>Key information</h2>
					<a href="<?php echo esc_url( home_url( '/tools' ) ); ?>">Practical tools</a>
					<a href="<?php echo esc_url( home_url( '/sandy' ) ); ?>">Co-production coach</a>
					<a href="<?php echo esc_url( home_url( '/commissioners-digital-toolkit' ) ); ?>">Digital commissioning toolkit</a>
					<a href="<?php echo esc_url( home_url( '/our-impact' ) ); ?>">Our impact</a>
					<a href="<?php echo esc_url( home_url( '/leadership-team' ) ); ?>">Meet the leadership team</a>
					<a href="<?php echo esc_url( home_url( '/privacy-policy' ) ); ?>">Privacy policy</a>
					<a href="<?php echo esc_url( home_url( '/accessibility-statement' ) ); ?>">Accessibility statement</a>
					<a href="<?php echo esc_url( home_url( '/terms-conditions' ) ); ?>">Terms &amp; conditions</a>
					<a href="<?php echo esc_url( home_url( '/intellectual-property' ) ); ?>">Intellectual property</a>
					<a href="<?php echo esc_url( home_url( '/developed-with-carers' ) ); ?>">Built with carers &amp; professionals</a>
					<a href="<?php echo esc_url( home_url( '/contact-us' ) ); ?>">Contact us</a>
					<a href="<?php echo esc_url( home_url( '/#book-a-call' ) ); ?>">Meet us / book a call</a>
				</div>
			</div>
			<div class="bpp-shell bpp-footer-bottom">
				<span>Copyright &copy; <?php echo esc_html( gmdate( 'Y' ) ); ?> Bridgit Care</span>
				<span>Bridgit is a trademark of Upstream Outcomes Limited</span>
			</div>
		</footer>

		<dialog class="bpp-support-dialog" data-bpp-support-dialog aria-labelledby="bpp-support-dialog-title">
			<div class="bpp-support-inner">
				<div class="bpp-support-heading">
					<div>
						<p class="bpp-eyebrow">Find the right Bridgit service</p>
						<h2 id="bpp-support-dialog-title">Who are you looking to support?</h2>
						<p>Choose the area that best matches what you need.</p>
					</div>
					<button class="bpp-support-close" type="button" aria-label="Close support choices" data-bpp-support-close>&times;</button>
				</div>
				<div class="bpp-support-grid">
					<?php foreach ( self::support_links() as $support ) : ?>
						<a class="bpp-support-option" href="<?php echo esc_url( $support[2] ); ?>">
							<img src="<?php echo esc_url( self::ORIGIN . '/images/support-selector/' . $support[3] ); ?>" alt="" width="720" height="540" loading="lazy">
							<span class="bpp-support-copy"><strong><?php echo esc_html( $support[0] ); ?></strong><small><?php echo esc_html( $support[1] ); ?></small></span>
						</a>
					<?php endforeach; ?>
				</div>
			</div>
		</dialog>

		<script>
		(function () {
			var menuButton = document.querySelector('[data-bpp-menu-button]');
			var menu = document.querySelector('[data-bpp-menu]');
			var dialog = document.querySelector('[data-bpp-support-dialog]');
			var closeButton = document.querySelector('[data-bpp-support-close]');

			if (menuButton && menu) {
				menuButton.addEventListener('click', function () {
					var isOpen = menuButton.getAttribute('aria-expanded') === 'true';
					menuButton.setAttribute('aria-expanded', String(!isOpen));
					menu.classList.toggle('bpp-is-open', !isOpen);
				});
			}

			document.querySelectorAll('[data-bpp-support-open]').forEach(function (opener) {
				opener.addEventListener('click', function (event) {
					event.preventDefault();
					if (dialog && typeof dialog.showModal === 'function') {
						dialog.showModal();
					}
				});
			});

			if (closeButton && dialog) {
				closeButton.addEventListener('click', function () { dialog.close(); });
				dialog.addEventListener('click', function (event) {
					if (event.target === dialog) { dialog.close(); }
				});
			}
		}());
		</script>
		<?php
	}

	private static function default_routes() {
		return array(
			'/',
			'/carer-services',
			'/young-carers',
			'/employers',
			'/local-authorities',
			'/care-leavers',
			'/healthy-ageing',
			'/social-enterprises',
			'/nhs',
			'/corporate-partners',
			'/leadership-team',
			'/responsible-ai',
			'/social-investors',
			'/our-impact',
			'/tools',
			'/sandy',
			'/commissioners-digital-toolkit',
			'/digital-readiness-review',
			'/demand-capacity-planner',
			'/pathway-mapper',
			'/responsible-ai-action-plan',
			'/partnership-builder',
			'/social-impact-advisor',
		);
	}

	public static function register_settings() {
		register_setting(
			'bridgit_page_publisher',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => array(
					'enabled'    => 0,
					'brand_blog' => 1,
					'routes'     => implode( "\n", self::default_routes() ),
				),
			)
		);
	}

	public static function sanitize_settings( $input ) {
		$current = self::settings();
		$output  = array(
			'enabled'    => empty( $input['enabled'] ) ? 0 : 1,
			'brand_blog' => empty( $input['brand_blog'] ) ? 0 : 1,
			'routes'     => isset( $input['routes'] ) ? self::sanitize_routes( $input['routes'] ) : $current['routes'],
		);

		self::purge_cache();

		return $output;
	}

	private static function sanitize_routes( $raw_routes ) {
		$lines  = preg_split( '/\R/', (string) $raw_routes );
		$routes = array();

		foreach ( $lines as $line ) {
			$route = self::normalize_path( trim( $line ) );

			if ( '' === $route || self::is_protected_path( $route ) ) {
				continue;
			}

			$routes[] = $route;
		}

		$routes = array_values( array_unique( $routes ) );

		return implode( "\n", $routes );
	}

	private static function is_protected_path( $path ) {
		$protected = array( '/blog', '/wp-admin', '/wp-json', '/wp-login.php', '/wp-content', '/wp-includes', '/feed' );

		foreach ( $protected as $prefix ) {
			if ( $path === $prefix || 0 === strpos( $path, $prefix . '/' ) ) {
				return true;
			}
		}

		return false;
	}

	private static function settings() {
		$defaults = array(
			'enabled'    => 0,
			'brand_blog' => 1,
			'routes'     => implode( "\n", self::default_routes() ),
		);

		return wp_parse_args( get_option( self::OPTION_KEY, array() ), $defaults );
	}

	private static function configured_routes() {
		$settings = self::settings();
		$routes   = preg_split( '/\R/', (string) $settings['routes'] );

		return array_values( array_filter( array_map( array( __CLASS__, 'normalize_path' ), $routes ) ) );
	}

	private static function normalize_path( $path ) {
		$path = '/' . ltrim( (string) $path, '/' );
		$path = preg_replace( '#/+#', '/', $path );

		if ( false !== strpos( $path, '..' ) ) {
			return '';
		}

		return '/' === $path ? '/' : untrailingslashit( $path );
	}

	private static function current_path() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );

		return self::normalize_path( $path ? $path : '/' );
	}

	private static function is_preview_request() {
		return isset( $_GET['bridgit_preview'] )
			&& '1' === sanitize_text_field( wp_unslash( $_GET['bridgit_preview'] ) )
			&& current_user_can( 'manage_options' );
	}

	private static function should_proxy() {
		$method = isset( $_SERVER['REQUEST_METHOD'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) ) : 'GET';
		$is_json = function_exists( 'wp_is_json_request' ) && wp_is_json_request();

		if ( ! in_array( $method, array( 'GET', 'HEAD' ), true ) || is_admin() || wp_doing_ajax() || $is_json ) {
			return false;
		}

		$settings = self::settings();
		if ( empty( $settings['enabled'] ) && ! self::is_preview_request() ) {
			return false;
		}

		$path = self::current_path();

		return ! self::is_protected_path( $path ) && in_array( $path, self::configured_routes(), true );
	}

	public static function maybe_serve_managed_page() {
		if ( ! self::should_proxy() ) {
			return;
		}

		if ( in_array( self::current_path(), array( '/young-carers', '/employers' ), true ) ) {
			wp_safe_redirect( home_url( '/carer-services/' ), 301, 'Bridgit Page Publisher' );
			exit;
		}

		self::$managed_request = true;
		self::render_proxy();
		exit;
	}

	public static function render_proxy() {
		if ( ! self::$managed_request ) {
			return;
		}

		$path      = self::current_path();
		$cache_key = 'bridgit_pp_' . md5( $path );
		$cached    = get_transient( $cache_key );
		$force     = self::is_preview_request() && isset( $_GET['bridgit_refresh'] );

		if ( ! $force && is_array( $cached ) && isset( $cached['fetched_at'] ) && ( time() - (int) $cached['fetched_at'] ) < self::CACHE_FRESH_FOR ) {
			self::send_response( $cached, true );
			return;
		}

		$origin_url = self::ORIGIN . ( '/' === $path ? '/' : trailingslashit( $path ) );
		$response   = wp_remote_get(
			$origin_url,
			array(
				'timeout'     => 15,
				'redirection' => 2,
				'sslverify'   => true,
				'headers'     => array(
					'Accept'     => 'text/html,application/xhtml+xml',
					'User-Agent' => 'Bridgit-WordPress-Publisher/1.0',
				),
			)
		);

		$diagnostic = '';

		if ( ! is_wp_error( $response ) ) {
			$status = (int) wp_remote_retrieve_response_code( $response );
			$body   = wp_remote_retrieve_body( $response );

			if ( $status >= 200 && $status < 300 && '' !== $body ) {
				$record = array(
					'status'       => $status,
					'content_type' => wp_remote_retrieve_header( $response, 'content-type' ),
					'body'         => self::rewrite_asset_urls( $body ),
					'fetched_at'   => time(),
				);

				set_transient( $cache_key, $record, self::CACHE_KEEP_FOR );
				self::remember_cache_key( $cache_key );
				self::send_response( $record, false );
				return;
			}

			$diagnostic = sprintf( 'Cloudflare returned HTTP %d with an empty or unusable response.', $status );
		} else {
			$diagnostic = $response->get_error_message();
		}

		if ( is_array( $cached ) && ! empty( $cached['body'] ) ) {
			self::send_response( $cached, true, true );
			return;
		}

		status_header( 502 );
		header( 'Content-Type: text/html; charset=UTF-8' );
		header( 'Cache-Control: no-store' );
		echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Bridgit is temporarily unavailable</title></head><body><main style="max-width:44rem;margin:10vh auto;padding:2rem;font:18px/1.6 system-ui"><h1>We will be back shortly.</h1><p>The Bridgit website could not be loaded just now. Please try again in a few moments.</p>';

		if ( self::is_preview_request() && '' !== $diagnostic ) {
			echo '<p><strong>Administrator diagnostic:</strong></p><pre style="white-space:pre-wrap;background:#f4f1f8;padding:1rem;border-radius:.5rem">' . esc_html( $diagnostic ) . '</pre>';
		}

		echo '</main></body></html>';
	}

	private static function rewrite_asset_urls( $html ) {
		$origin = self::ORIGIN;
		$pairs  = array(
			'="/_astro/'       => '="' . $origin . '/_astro/',
			"='/_astro/"       => "='" . $origin . '/_astro/',
			'="/images/'       => '="' . $origin . '/images/',
			"='/images/"       => "='" . $origin . '/images/',
			'url(/images/'      => 'url(' . $origin . '/images/',
			'url("/images/'    => 'url("' . $origin . '/images/',
			"url('/images/"    => "url('" . $origin . '/images/',
			'url(/_astro/'      => 'url(' . $origin . '/_astro/',
			'url("/_astro/'    => 'url("' . $origin . '/_astro/',
			"url('/_astro/"    => "url('" . $origin . '/_astro/',
		);

		return strtr( $html, $pairs );
	}

	private static function send_response( $record, $from_cache, $stale = false ) {
		$status       = isset( $record['status'] ) ? (int) $record['status'] : 200;
		$content_type = ! empty( $record['content_type'] ) ? $record['content_type'] : 'text/html; charset=UTF-8';
		$is_head      = isset( $_SERVER['REQUEST_METHOD'] ) && 'HEAD' === strtoupper( sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ) ) );

		status_header( $status );
		header( 'Content-Type: ' . $content_type );
		header( 'Cache-Control: public, max-age=60, stale-while-revalidate=300' );
		header( 'X-Content-Type-Options: nosniff' );
		header( 'X-Bridgit-Publisher: ' . ( $from_cache ? ( $stale ? 'stale' : 'cache' ) : 'origin' ) );

		if ( self::is_preview_request() ) {
			header( 'X-Robots-Tag: noindex, nofollow' );
		}

		if ( ! $is_head ) {
			echo $record['body']; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted fixed Cloudflare origin.
		}
	}

	private static function remember_cache_key( $key ) {
		$keys = get_option( self::CACHE_KEYS_KEY, array() );
		$keys = is_array( $keys ) ? $keys : array();

		if ( ! in_array( $key, $keys, true ) ) {
			$keys[] = $key;
			update_option( self::CACHE_KEYS_KEY, $keys, false );
		}
	}

	public static function purge_cache() {
		$keys = get_option( self::CACHE_KEYS_KEY, array() );

		if ( is_array( $keys ) ) {
			foreach ( $keys as $key ) {
				delete_transient( $key );
			}
		}

		delete_option( self::CACHE_KEYS_KEY );
	}

	public static function handle_cache_purge() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do that.', 'bridgit-page-publisher' ) );
		}

		check_admin_referer( 'bridgit_publisher_purge' );
		self::purge_cache();
		wp_safe_redirect( add_query_arg( 'cache_purged', '1', admin_url( 'options-general.php?page=bridgit-page-publisher' ) ) );
		exit;
	}

	public static function add_settings_page() {
		add_options_page(
			__( 'Bridgit Page Publisher', 'bridgit-page-publisher' ),
			__( 'Bridgit Publisher', 'bridgit-page-publisher' ),
			'manage_options',
			'bridgit-page-publisher',
			array( __CLASS__, 'render_settings_page' )
		);
	}

	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Bridgit Page Publisher', 'bridgit-page-publisher' ); ?></h1>
			<p><strong><?php esc_html_e( 'WordPress remains in control.', 'bridgit-page-publisher' ); ?></strong> <?php esc_html_e( 'Only the exact routes below can be served from the approved Cloudflare production site. Blog, post, media, admin and REST routes are always protected.', 'bridgit-page-publisher' ); ?></p>
			<p><?php esc_html_e( 'Cloudflare origin:', 'bridgit-page-publisher' ); ?> <code><?php echo esc_html( self::ORIGIN ); ?></code></p>

			<?php if ( isset( $_GET['cache_purged'] ) ) : ?>
				<div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Bridgit page cache cleared.', 'bridgit-page-publisher' ); ?></p></div>
			<?php endif; ?>

			<form method="post" action="options.php">
				<?php settings_fields( 'bridgit_page_publisher' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Public publishing', 'bridgit-page-publisher' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[enabled]" value="1" <?php checked( ! empty( $settings['enabled'] ) ); ?>> <?php esc_html_e( 'Serve the selected routes publicly from Cloudflare', 'bridgit-page-publisher' ); ?></label>
							<p class="description"><?php esc_html_e( 'Leave this off after installation. Administrators can safely preview first.', 'bridgit-page-publisher' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'WordPress content branding', 'bridgit-page-publisher' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[brand_blog]" value="1" <?php checked( ! empty( $settings['brand_blog'] ) ); ?>> <?php esc_html_e( 'Use the new Bridgit navigation and footer on the blog and key information pages', 'bridgit-page-publisher' ); ?></label>
							<p class="description"><?php esc_html_e( 'WordPress continues to manage the content; only the surrounding navigation, support chooser and footer are replaced.', 'bridgit-page-publisher' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="bridgit-publisher-routes"><?php esc_html_e( 'Managed routes', 'bridgit-page-publisher' ); ?></label></th>
						<td>
							<textarea id="bridgit-publisher-routes" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[routes]" rows="13" cols="52" class="large-text code"><?php echo esc_textarea( $settings['routes'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One exact path per line. Protected WordPress paths are rejected automatically.', 'bridgit-page-publisher' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Save publishing settings', 'bridgit-page-publisher' ) ); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'Safe preview', 'bridgit-page-publisher' ); ?></h2>
			<p><?php esc_html_e( 'While public publishing is off, a logged-in administrator can preview any configured route by adding:', 'bridgit-page-publisher' ); ?> <code>?bridgit_preview=1&amp;bridgit_refresh=1</code></p>
			<p><a class="button button-secondary" href="<?php echo esc_url( add_query_arg( array( 'bridgit_preview' => '1', 'bridgit_refresh' => '1' ), home_url( '/' ) ) ); ?>" target="_blank" rel="noopener"><?php esc_html_e( 'Preview the new homepage', 'bridgit-page-publisher' ); ?></a></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="bridgit_publisher_purge">
				<?php wp_nonce_field( 'bridgit_publisher_purge' ); ?>
				<?php submit_button( __( 'Clear Bridgit page cache', 'bridgit-page-publisher' ), 'secondary', 'submit', false ); ?>
			</form>

			<hr>
			<h2><?php esc_html_e( 'ElevenLabs lead webhook', 'bridgit-page-publisher' ); ?></h2>
			<p><?php esc_html_e( 'This protected endpoint accepts consented sales enquiries from the Bridgit website coaches and sends them to contact@bridgit.care.', 'bridgit-page-publisher' ); ?></p>
			<p><label for="bridgit-lead-endpoint"><strong><?php esc_html_e( 'Endpoint', 'bridgit-page-publisher' ); ?></strong></label><br><input id="bridgit-lead-endpoint" class="large-text code" type="text" readonly value="<?php echo esc_attr( rest_url( 'bridgit/v1/lead' ) ); ?>"></p>
			<p class="description"><?php esc_html_e( 'The authentication secret is stored outside WordPress; only a one-way verification hash is present in this plugin.', 'bridgit-page-publisher' ); ?></p>
		</div>
		<?php
	}
}

register_activation_hook( __FILE__, array( 'Bridgit_Page_Publisher', 'activate' ) );
Bridgit_Page_Publisher::init();
