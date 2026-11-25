import { createElement } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import Markdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import '../css/chat-markdown.css';

function remarkWhatsAppEmphasis() {
    return (tree, file) => {
        const source = String(file);

        function visit(node) {
            if (node.type === 'emphasis' && source[node.position?.start.offset] === '*') {
                node.type = 'strong';
            }
            node.children?.forEach(visit);
        }

        visit(tree);
    };
}

const components = {
    a: ({ href, children }) => href
        ? createElement('a', { href, target: '_blank', rel: 'noopener noreferrer' }, children)
        : children,
    img: ({ alt }) => alt,
    table: ({ children }) => createElement('div', { className: 'chat-markdown-table' },
        createElement('table', null, children)),
};

export function renderChatMarkdown(source) {
    if (typeof source !== 'string' || !source) return '';

    return renderToStaticMarkup(createElement(Markdown, {
        children: source,
        remarkPlugins: [remarkGfm, remarkWhatsAppEmphasis],
        skipHtml: true,
        components,
    }));
}

if (typeof window !== 'undefined') window.renderChatMarkdown = renderChatMarkdown;
