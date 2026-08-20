import React, { forwardRef } from 'react';
import { Form } from 'formik';
import styled from 'styled-components/macro';
import FlashMessageRender from '@/components/FlashMessageRender';
import tw from 'twin.macro';

type Props = React.DetailedHTMLProps<React.FormHTMLAttributes<HTMLFormElement>, HTMLFormElement> & {
    title?: string;
    subtitle?: string;
};

const Card = styled.div`
    ${tw`relative w-full rounded-2xl border border-neutral-700/70 bg-neutral-900/60 backdrop-blur-xl px-6 py-8 sm:px-10`};
    box-shadow:
        0 0 0 1px rgba(148, 163, 184, 0.04),
        0 30px 70px -20px rgba(0, 0, 0, 0.8),
        inset 0 1px 0 rgba(255, 255, 255, 0.03);
`;

const Glow = styled.div`
    ${tw`absolute -top-20 left-1/2 transform -translate-x-1/2 w-72 h-72 rounded-full pointer-events-none`};
    background: radial-gradient(circle, rgba(34, 211, 238, 0.22), rgba(139, 92, 246, 0.08) 55%, transparent 72%);
    filter: blur(14px);
`;

export default forwardRef<HTMLFormElement, Props>(({ title, subtitle, ...props }, ref) => (
    <div css={tw`w-full max-w-md mx-auto px-4`}>
        <Card>
            <Glow />
            <div css={tw`relative flex flex-col items-center`}>
                <img src={'/assets/svgs/hoston-games.svg'} css={tw`block w-56 sm:w-64`} />
                {title && (
                    <h2 css={tw`text-center text-2xl font-header font-semibold text-neutral-100 mt-4 mb-1`}>{title}</h2>
                )}
                {subtitle && <p css={tw`text-center text-neutral-400 text-sm mb-6`}>{subtitle}</p>}
                {!subtitle && <div css={tw`h-6`} />}
            </div>
            <FlashMessageRender css={tw`mb-4`} />
            <Form {...props} ref={ref}>
                {props.children}
            </Form>
        </Card>
        <p css={tw`text-center text-neutral-500 text-xs mt-6`}>
            &copy; {new Date().getFullYear()}&nbsp;
            <a
                rel={'noopener nofollow noreferrer'}
                href={'https://host-on.games'}
                target={'_blank'}
                css={tw`no-underline text-neutral-400 hover:text-neutral-200`}
            >
                Host-On.Games
            </a>
            &nbsp;·&nbsp;
            <a
                rel={'noopener nofollow noreferrer'}
                href={'https://pterodactyl.io'}
                target={'_blank'}
                css={tw`no-underline text-neutral-600 hover:text-neutral-400`}
            >
                Powered by Pterodactyl
            </a>
        </p>
    </div>
));
