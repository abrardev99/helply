// Credit: https://usehooks-ts.com/
import { useState } from 'react';

export type CopiedValue = string | null;
export type CopyFn = (text: string) => Promise<boolean>;
export type UseClipboardReturn = [CopiedValue, CopyFn];

/**
 * Legacy clipboard copy for non-secure contexts (plain HTTP dev domains) where the
 * async Clipboard API is unavailable.
 */
function copyWithExecCommand(text: string): boolean {
    if (typeof document === 'undefined') {
        return false;
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();

    let succeeded = false;

    try {
        succeeded = document.execCommand('copy');
    } catch {
        succeeded = false;
    }

    document.body.removeChild(textarea);

    return succeeded;
}

export function useClipboard(): UseClipboardReturn {
    const [copiedText, setCopiedText] = useState<CopiedValue>(null);

    const copy: CopyFn = async (text) => {
        try {
            // The async Clipboard API is only available in secure contexts (HTTPS or
            // localhost). Fall back to execCommand for plain-HTTP dev domains.
            if (navigator?.clipboard && window.isSecureContext) {
                await navigator.clipboard.writeText(text);
            } else if (!copyWithExecCommand(text)) {
                throw new Error('execCommand copy failed');
            }

            setCopiedText(text);

            return true;
        } catch (error) {
            console.warn('Copy failed', error);
            setCopiedText(null);

            return false;
        }
    };

    return [copiedText, copy];
}
