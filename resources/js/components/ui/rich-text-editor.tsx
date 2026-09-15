import { type Editor, EditorContent, useEditor } from '@tiptap/react';
import StarterKit from '@tiptap/starter-kit';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { richTextProseClass } from '@/lib/rich-text';
import { cn } from '@/lib/utils';
import {
    Bold,
    Code,
    Heading1,
    Heading2,
    Heading3,
    Italic,
    Link as LinkIcon,
    Link2Off,
    List,
    ListOrdered,
    Minus,
    Quote,
    Redo2,
    Strikethrough,
    Underline,
    Undo2,
} from 'lucide-react';

function ToolbarButton({
    label,
    active,
    disabled,
    onClick,
    children,
}: {
    label: string;
    active?: boolean;
    disabled?: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Button
                    type="button"
                    variant="ghost"
                    size="icon"
                    className={cn('size-7', active && 'bg-accent')}
                    disabled={disabled}
                    onClick={onClick}
                >
                    {children}
                    <span className="sr-only">{label}</span>
                </Button>
            </TooltipTrigger>
            <TooltipContent>{label}</TooltipContent>
        </Tooltip>
    );
}

function Toolbar({ editor }: { editor: Editor }) {
    function setLink() {
        const previousUrl = editor.getAttributes('link').href as
            | string
            | undefined;
        const url = window.prompt('Link URL', previousUrl ?? 'https://');

        if (url === null) {
            return;
        }

        if (url === '') {
            editor.chain().focus().extendMarkRange('link').unsetLink().run();
            return;
        }

        editor
            .chain()
            .focus()
            .extendMarkRange('link')
            .setLink({ href: url })
            .run();
    }

    return (
        <div className="border-input flex flex-wrap items-center gap-0.5 border-b p-1">
            <ToolbarButton
                label="Undo"
                disabled={!editor.can().undo()}
                onClick={() => editor.chain().focus().undo().run()}
            >
                <Undo2 className="size-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Redo"
                disabled={!editor.can().redo()}
                onClick={() => editor.chain().focus().redo().run()}
            >
                <Redo2 className="size-4" />
            </ToolbarButton>

            <div className="bg-border mx-1 h-5 w-px" />

            <ToolbarButton
                label="Heading 1"
                active={editor.isActive('heading', { level: 1 })}
                onClick={() =>
                    editor.chain().focus().toggleHeading({ level: 1 }).run()
                }
            >
                <Heading1 className="size-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Heading 2"
                active={editor.isActive('heading', { level: 2 })}
                onClick={() =>
                    editor.chain().focus().toggleHeading({ level: 2 }).run()
                }
            >
                <Heading2 className="size-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Heading 3"
                active={editor.isActive('heading', { level: 3 })}
                onClick={() =>
                    editor.chain().focus().toggleHeading({ level: 3 }).run()
                }
            >
                <Heading3 className="size-4" />
            </ToolbarButton>

            <div className="bg-border mx-1 h-5 w-px" />

            <ToolbarButton
                label="Bold"
                active={editor.isActive('bold')}
                onClick={() => editor.chain().focus().toggleBold().run()}
            >
                <Bold className="size-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Italic"
                active={editor.isActive('italic')}
                onClick={() => editor.chain().focus().toggleItalic().run()}
            >
                <Italic className="size-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Underline"
                active={editor.isActive('underline')}
                onClick={() => editor.chain().focus().toggleUnderline().run()}
            >
                <Underline className="size-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Strikethrough"
                active={editor.isActive('strike')}
                onClick={() => editor.chain().focus().toggleStrike().run()}
            >
                <Strikethrough className="size-4" />
            </ToolbarButton>

            <div className="bg-border mx-1 h-5 w-px" />

            <ToolbarButton
                label="Bullet list"
                active={editor.isActive('bulletList')}
                onClick={() =>
                    editor.chain().focus().toggleBulletList().run()
                }
            >
                <List className="size-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Numbered list"
                active={editor.isActive('orderedList')}
                onClick={() =>
                    editor.chain().focus().toggleOrderedList().run()
                }
            >
                <ListOrdered className="size-4" />
            </ToolbarButton>

            <div className="bg-border mx-1 h-5 w-px" />

            <ToolbarButton
                label="Quote"
                active={editor.isActive('blockquote')}
                onClick={() =>
                    editor.chain().focus().toggleBlockquote().run()
                }
            >
                <Quote className="size-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Code block"
                active={editor.isActive('codeBlock')}
                onClick={() =>
                    editor.chain().focus().toggleCodeBlock().run()
                }
            >
                <Code className="size-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Horizontal rule"
                onClick={() => editor.chain().focus().setHorizontalRule().run()}
            >
                <Minus className="size-4" />
            </ToolbarButton>

            <div className="bg-border mx-1 h-5 w-px" />

            <ToolbarButton
                label={editor.isActive('link') ? 'Edit link' : 'Add link'}
                active={editor.isActive('link')}
                onClick={setLink}
            >
                <LinkIcon className="size-4" />
            </ToolbarButton>
            <ToolbarButton
                label="Remove link"
                disabled={!editor.isActive('link')}
                onClick={() => editor.chain().focus().unsetLink().run()}
            >
                <Link2Off className="size-4" />
            </ToolbarButton>
        </div>
    );
}

export function RichTextEditor({
    id,
    value,
    onChange,
    placeholder,
    className,
}: {
    id?: string;
    value: string;
    onChange: (html: string) => void;
    placeholder?: string;
    className?: string;
}) {
    const editor = useEditor({
        extensions: [
            StarterKit.configure({
                heading: { levels: [1, 2, 3] },
                link: { openOnClick: false, autolink: true },
            }),
        ],
        content: value,
        editorProps: {
            attributes: {
                id: id ?? '',
                class: cn(
                    'min-h-24 px-3 py-2 text-sm focus:outline-none',
                    richTextProseClass,
                ),
                ...(placeholder ? { 'data-placeholder': placeholder } : {}),
            },
        },
        onUpdate: ({ editor }) => onChange(editor.getHTML()),
    });

    useEffect(() => {
        if (editor && value !== editor.getHTML()) {
            editor.commands.setContent(value);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value, editor]);

    if (!editor) {
        return null;
    }

    return (
        <div
            className={cn(
                'border-input focus-within:border-ring focus-within:ring-ring/50 rounded-md border shadow-xs transition-[color,box-shadow] focus-within:ring-[3px]',
                className,
            )}
        >
            <Toolbar editor={editor} />
            <EditorContent editor={editor} />
        </div>
    );
}
