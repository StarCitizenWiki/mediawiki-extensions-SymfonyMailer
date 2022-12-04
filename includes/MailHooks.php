<?php
declare( strict_types=1 );

/**
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along
 * with this program; if not, write to the Free Software Foundation, Inc.,
 * 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
 *
 * @file
 */

namespace MediaWiki\Extension\SymfonyMailer;

use ConfigException;
use MediaWiki\Hook\AlternateUserMailerHook;
use MediaWiki\MediaWikiServices;
use Symfony\Component\Mailer\Exception\InvalidArgumentException;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\Transport;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

class MailHooks implements AlternateUserMailerHook {

	/**
	 * @inheritDoc
	 */
	public function onAlternateUserMailer( $headers, $to, $from, $subject, $body ) {
		$message = ( new Email() )
			->subject( $subject )
			->from( new Address( $from->address, $from->name ) )
			->html( $body );

		$returnPath = $headers['Return-Path'];
		$message->returnPath( $returnPath );

		try {
			$transport = self::getTransport();
		} catch ( ConfigException | InvalidArgumentException | TransportException $e ) {
			wfDebugLog( 'SymfonyMailer', "Transport configuration for SymfonyMailer failed: $e" );
			return $e;
		}

		if ( $transport === null ) {
			wfLogWarning( 'SymfonyMailer: Tried to use un-configured transport. Is $wgSMTP set correctly?' );
			return false;
		}

		wfDebug( "Sending mail via Symfony::Mail\n" );

		foreach ( $to as $recip ) {
			$message->to( new Address( $recip->address, $recip->name ) );
			try {
				$transport->send( $message );
			} catch ( TransportExceptionInterface $e ) {
				wfDebugLog( 'SymfonyMailer', "Symfony Mailer failed: $e" );
				return $e;
			}
		}

		// Alternate Mailer hooks should return false to skip regular false sending
		return false;
	}

	/**
	 * Generate the SymfonyMailer transport object
	 *
	 * @return Transport\TransportInterface|null
	 * @throws ConfigException|InvalidArgumentException
	 */
	protected static function getTransport(): ?Transport\TransportInterface {
		$smtp = MediaWikiServices::getInstance()->getMainConfig()->get( 'SMTP' );

		static $transport = null;

		if ( !$transport ) {
			if ( is_array( $smtp ) ) {
				$transport = Transport::fromDsn( sprintf(
					'smtp://%s:%s@%s:%d',
					$smtp['username'],
					$smtp['password'],
					$smtp['host'],
					$smtp['port'],
				) );
			} else {
				throw new TransportException();
			}
		}

		return $transport;
	}
}
