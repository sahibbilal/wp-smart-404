<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class WS404_Matcher {

    const API_URL = 'https://api.anthropic.com/v1/messages';
    const MODEL   = 'claude-haiku-4-5';

    private $api_key;

    public function __construct() {
        $this->api_key = get_option( 'ws404_claude_api_key', '' );
    }

    /**
     * Find the best matching page/post for a broken URL.
     *
     * @param string $broken_url  The 404 URL path e.g. /services/web-desing
     * @return array|WP_Error  { url, title, confidence, reason }
     */
    public function find_match( $broken_url ) {
        if ( empty( $this->api_key ) ) {
            return new WP_Error( 'no_api_key', 'Claude API key not configured. Go to Smart 404 → Settings.' );
        }

        $site_pages = $this->get_site_pages();
        if ( empty( $site_pages ) ) {
            return new WP_Error( 'no_pages', 'No published pages or posts found to match against.' );
        }

        $pages_list = '';
        foreach ( $site_pages as $page ) {
            $pages_list .= "- [{$page['title']}]({$page['url']})\n";
        }

        $prompt = "A visitor hit a 404 error on this WordPress site.

BROKEN URL: {$broken_url}

Here are all the published pages and posts on the site:
{$pages_list}

Your job: find the single best matching page for the broken URL. Consider:
- Slug similarity (typos, word order, missing/extra words)
- Topic relevance based on the URL words
- Likely user intent

Return ONLY valid JSON in this exact format, nothing else:
{
  \"url\": \"https://example.com/the-best-match\",
  \"title\": \"Page Title Here\",
  \"confidence\": \"high\",
  \"reason\": \"One sentence explaining why this is the best match.\"
}

confidence must be: high, medium, or low.
If no reasonable match exists, return confidence: \"low\" and explain in reason.";

        $response = wp_remote_post(
            self::API_URL,
            array(
                'timeout' => 30,
                'headers' => array(
                    'x-api-key'         => $this->api_key,
                    'anthropic-version' => '2023-06-01',
                    'content-type'      => 'application/json',
                ),
                'body' => wp_json_encode( array(
                    'model'      => self::MODEL,
                    'max_tokens' => 256,
                    'messages'   => array(
                        array( 'role' => 'user', 'content' => $prompt ),
                    ),
                ) ),
            )
        );

        if ( is_wp_error( $response ) ) return $response;

        $code = wp_remote_retrieve_response_code( $response );
        $body = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $body['error']['message'] ?? 'API error (HTTP ' . $code . ')';
            return new WP_Error( 'api_error', $msg );
        }

        $raw  = $body['content'][0]['text'] ?? '';
        $json = json_decode( $raw, true );

        if ( ! $json || ! isset( $json['url'] ) ) {
            return new WP_Error( 'parse_error', 'Could not parse Claude response. Raw: ' . substr( $raw, 0, 200 ) );
        }

        return $json;
    }

    private function get_site_pages() {
        $posts = get_posts( array(
            'post_type'      => array( 'post', 'page' ),
            'post_status'    => 'publish',
            'posts_per_page' => 100,
            'fields'         => 'all',
        ) );

        $pages = array();
        foreach ( $posts as $post ) {
            $pages[] = array(
                'title' => $post->post_title,
                'url'   => get_permalink( $post->ID ),
            );
        }
        return $pages;
    }
}
