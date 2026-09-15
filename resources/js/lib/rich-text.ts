// Shared prose styling for both authoring (rich-text-editor.tsx) and
// read-only (rich-text-content.tsx) rendering, so saved content looks the
// same in the editor as it does everywhere it's displayed.
export const richTextProseClass =
    '[&_p]:mb-2 last:[&_p]:mb-0 ' +
    '[&_ul]:list-disc [&_ul]:pl-5 [&_ol]:list-decimal [&_ol]:pl-5 ' +
    '[&_h1]:mt-3 [&_h1]:mb-2 [&_h1]:text-xl [&_h1]:font-bold ' +
    '[&_h2]:mt-3 [&_h2]:mb-2 [&_h2]:text-lg [&_h2]:font-bold ' +
    '[&_h3]:mt-3 [&_h3]:mb-2 [&_h3]:text-base [&_h3]:font-bold ' +
    '[&_blockquote]:border-l-2 [&_blockquote]:pl-3 [&_blockquote]:italic [&_blockquote]:text-muted-foreground ' +
    '[&_code]:bg-muted [&_code]:rounded [&_code]:px-1 [&_code]:py-0.5 [&_code]:text-xs ' +
    '[&_pre]:bg-muted [&_pre]:overflow-x-auto [&_pre]:rounded [&_pre]:p-2 [&_pre_code]:bg-transparent [&_pre_code]:p-0 ' +
    '[&_hr]:border-border [&_hr]:my-3 ' +
    '[&_a]:text-primary [&_a]:underline [&_a]:underline-offset-2 ' +
    '[&_u]:underline [&_s]:line-through';
