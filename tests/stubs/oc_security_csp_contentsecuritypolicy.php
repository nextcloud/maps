<?php

declare(strict_types=1);
/**
 * SPDX-FileCopyrightText: 2016-2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */
namespace OC\Security\CSP;

/**
 * Class ContentSecurityPolicy extends the public class and adds getter and setters.
 * This is necessary since we don't want to expose the setters and getters to the
 * public API.
 *
 * @package OC\Security\CSP
 */
class ContentSecurityPolicy extends \OCP\AppFramework\Http\ContentSecurityPolicy {
	public function isInlineScriptAllowed(): bool
 {
 }

	public function setInlineScriptAllowed(bool $inlineScriptAllowed): void
 {
 }

	public function isEvalScriptAllowed(): bool
 {
 }

	/**
	 * @deprecated 17.0.0 Unsafe eval should not be used anymore.
	 */
	public function setEvalScriptAllowed(bool $evalScriptAllowed): void
 {
 }

	public function isEvalWasmAllowed(): ?bool
 {
 }

	public function setEvalWasmAllowed(bool $evalWasmAllowed): void
 {
 }

	public function getAllowedScriptDomains(): array
 {
 }

	public function setAllowedScriptDomains(array $allowedScriptDomains): void
 {
 }

	public function isInlineStyleAllowed(): bool
 {
 }

	public function setInlineStyleAllowed(bool $inlineStyleAllowed): void
 {
 }

	public function getAllowedStyleDomains(): array
 {
 }

	public function setAllowedStyleDomains(array $allowedStyleDomains): void
 {
 }

	public function getAllowedImageDomains(): array
 {
 }

	public function setAllowedImageDomains(array $allowedImageDomains): void
 {
 }

	public function getAllowedConnectDomains(): array
 {
 }

	public function setAllowedConnectDomains(array $allowedConnectDomains): void
 {
 }

	public function getAllowedMediaDomains(): array
 {
 }

	public function setAllowedMediaDomains(array $allowedMediaDomains): void
 {
 }

	public function getAllowedObjectDomains(): array
 {
 }

	public function setAllowedObjectDomains(array $allowedObjectDomains): void
 {
 }

	public function getAllowedFrameDomains(): array
 {
 }

	public function setAllowedFrameDomains(array $allowedFrameDomains): void
 {
 }

	public function getAllowedFontDomains(): array
 {
 }

	public function setAllowedFontDomains($allowedFontDomains): void
 {
 }

	/**
	 * @deprecated 15.0.0 use FrameDomains and WorkerSrcDomains
	 */
	public function getAllowedChildSrcDomains(): array
 {
 }

	/**
	 * @param array $allowedChildSrcDomains
	 * @deprecated 15.0.0 use FrameDomains and WorkerSrcDomains
	 */
	public function setAllowedChildSrcDomains($allowedChildSrcDomains): void
 {
 }

	public function getAllowedFrameAncestors(): array
 {
 }

	/**
	 * @param array $allowedFrameAncestors
	 */
	public function setAllowedFrameAncestors($allowedFrameAncestors): void
 {
 }

	public function getAllowedWorkerSrcDomains(): array
 {
 }

	public function setAllowedWorkerSrcDomains(array $allowedWorkerSrcDomains): void
 {
 }

	public function getAllowedFormActionDomains(): array
 {
 }

	public function setAllowedFormActionDomains(array $allowedFormActionDomains): void
 {
 }


	public function getReportTo(): array
 {
 }

	public function setReportTo(array $reportTo): void
 {
 }

	public function isStrictDynamicAllowed(): bool
 {
 }

	public function setStrictDynamicAllowed(bool $strictDynamicAllowed): void
 {
 }

	public function isStrictDynamicAllowedOnScripts(): bool
 {
 }

	public function setStrictDynamicAllowedOnScripts(bool $strictDynamicAllowedOnScripts): void
 {
 }
}
