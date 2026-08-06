<?php
/**
 * Transactional persistence boundary for PrayerPop Chat.
 *
 * Chat controllers validate requests, authorize users, and send notifications.
 * This class owns the database mutations that must commit together.
 *
 * @package PrayerPop
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Prayer_Pop_Chat_Storage {
	/** @var string */
	private $conversations_table;

	/** @var string */
	private $messages_table;

	/** @var bool|null */
	private $transactional_storage = null;

	public function __construct( $conversations_table, $messages_table ) {
		$this->conversations_table = (string) $conversations_table;
		$this->messages_table      = (string) $messages_table;
	}

	/** Create a conversation, its first message, and optional initial fields atomically. */
	public function create_conversation( $conversation, $message, $initial_updates = array() ) {
		global $wpdb;
		if ( ! $this->start_transaction() ) {
			return 0;
		}
		$inserted = $wpdb->insert( $this->conversations_table, $conversation );
		$id       = (int) $wpdb->insert_id;
		if ( false === $inserted || $id <= 0 || ! $this->insert_message( $id, 'visitor', 0, $message ) ) {
			$this->rollback();
			return 0;
		}
		if ( ! empty( $initial_updates ) ) {
			$updated = $wpdb->update( $this->conversations_table, $initial_updates, array( 'id' => $id ) );
			if ( 1 !== (int) $updated ) {
				$this->rollback();
				return 0;
			}
		}
		if ( ! $this->commit() ) {
			$this->rollback();
			return 0;
		}
		return $id;
	}

	/** Persist a visitor or administrator message and its conversation summary atomically. */
	public function append_message( $conversation_id, $sender_type, $sender_user_id, $message, $excerpt ) {
		global $wpdb;
		if ( ! $this->start_transaction() ) {
			return false;
		}
		$column = 'admin' === $sender_type ? 'visitor_unread' : 'admin_unread';
		$now    = current_time( 'mysql', true );
		$table  = esc_sql( $this->conversations_table );
		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- The table is an internally generated WordPress table name and the column is whitelisted above.
		$sql = $wpdb->prepare( "UPDATE {$table} SET {$column}={$column}+1,last_sender=%s,last_message_excerpt=%s,last_message_at=%s,updated_at=%s WHERE id=%d", $sender_type, $excerpt, $now, $now, absint( $conversation_id ) );
		if ( ! $this->insert_message( $conversation_id, $sender_type, $sender_user_id, $message ) || 1 !== (int) $wpdb->query( $sql ) || ! $this->commit() ) { // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Prepared immediately above.
			$this->rollback();
			return false;
		}
		return true;
	}

	/** Add a standalone message for compatible Chat flows. */
	public function insert_message( $conversation_id, $sender_type, $sender_user_id, $message ) {
		global $wpdb;
		$inserted = $wpdb->insert( $this->messages_table, array(
			'conversation_id' => absint( $conversation_id ),
			'sender_type'     => $sender_type,
			'sender_user_id'  => absint( $sender_user_id ),
			'message'         => $message,
			'created_at'      => current_time( 'mysql', true ),
		) );
		return false !== $inserted && (int) $wpdb->insert_id > 0;
	}

	/** Delete a conversation and every child message atomically. */
	public function delete_conversation( $conversation_id ) {
		global $wpdb;
		$conversation_id = absint( $conversation_id );
		if ( $conversation_id <= 0 || ! $this->start_transaction() ) {
			return false;
		}
		$messages_deleted     = $wpdb->delete( $this->messages_table, array( 'conversation_id' => $conversation_id ), array( '%d' ) );
		$conversation_deleted = $wpdb->delete( $this->conversations_table, array( 'id' => $conversation_id ), array( '%d' ) );
		if ( false === $messages_deleted || 1 !== (int) $conversation_deleted || ! $this->commit() ) {
			$this->rollback();
			return false;
		}
		return true;
	}

	/** Start a transaction only when both tables are InnoDB. */
	private function start_transaction() {
		global $wpdb;
		if ( null === $this->transactional_storage ) {
			$this->transactional_storage = true;
			foreach ( array( $this->conversations_table, $this->messages_table ) as $table ) {
				$status = $wpdb->get_row( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name = %s', $table ) );
				if ( ! $status || empty( $status->Engine ) || 'innodb' !== strtolower( $status->Engine ) ) {
					$this->transactional_storage = false;
					break;
				}
			}
		}
		return $this->transactional_storage && false !== $wpdb->query( 'START TRANSACTION' );
	}

	private function commit() {
		global $wpdb;
		return false !== $wpdb->query( 'COMMIT' );
	}

	private function rollback() {
		global $wpdb;
		$wpdb->query( 'ROLLBACK' );
	}
}
