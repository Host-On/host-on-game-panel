import React, { useState } from 'react';
import useSWR from 'swr';
import { Link } from 'react-router-dom';
import tw from 'twin.macro';
import styled from 'styled-components/macro';
import PageContentBlock from '@/components/elements/PageContentBlock';
import Spinner from '@/components/elements/Spinner';
import http from '@/api/http';
import useFlash from '@/plugins/useFlash';

interface CatalogProfile {
    name: string;
    slug: string;
    cpu: number;
    memory: number;
    disk: number;
    game_memory: number;
    infrastructure_type: string;
    price: string | null;
}

interface CatalogEntry {
    id: string;
    name: string;
    slug: string;
    description: string | null;
    artwork: string | null;
    profiles: CatalogProfile[];
}

const Card = styled.div`
    ${tw`bg-neutral-800 rounded-lg p-6 border border-neutral-700 transition-colors duration-150 hover:border-cyan-600`};
`;

const GameTitle = styled.h2`
    ${tw`text-xl font-header font-medium text-neutral-100 mb-1`};
`;

export default () => {
    const { clearAndAddHttpError, addFlash } = useFlash();
    const [ordering, setOrdering] = useState<string | null>(null);

    const { data, error } = useSWR<CatalogEntry[]>('/api/client/hoston/catalog', async () => {
        const response = await http.get('/api/client/hoston/catalog');

        return response.data;
    });

    if (error) {
        clearAndAddHttpError({ key: 'games', error });
    }

    const order = (slug: string) => {
        setOrdering(slug);
        http.post('/api/client/hoston/order', { product: slug })
            .then(() => {
                addFlash({ type: 'success', key: 'games', message: 'Your game server is being provisioned. Check the dashboard shortly.' });
                setOrdering(null);
            })
            .catch((err) => {
                clearAndAddHttpError({ key: 'games', error: err });
                setOrdering(null);
            });
    };

    if (!data) {
        return <Spinner centered />;
    }

    return (
        <PageContentBlock title={'Games'} showFlashKey={'games'}>
            <div css={tw`mb-8`}>
                <h1 css={tw`text-3xl font-header font-medium text-neutral-100`}>Choose your game</h1>
                <p css={tw`text-neutral-400 mt-1`}>Every game server runs on its own isolated, dedicated virtual machine.</p>
            </div>
            <div css={tw`grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6`}>
                {data.map((entry) => (
                    <Card key={entry.slug}>
                        <GameTitle>{entry.name}</GameTitle>
                        <p css={tw`text-neutral-400 text-sm mb-4`}>{entry.description ?? ''}</p>
                        {entry.profiles.length === 0 ? (
                            <p css={tw`text-neutral-500 text-sm italic`}>Coming soon</p>
                        ) : (
                            <div css={tw`space-y-3`}>
                                {entry.profiles.map((profile) => (
                                    <div key={profile.slug} css={tw`flex items-center justify-between`}>
                                        <div>
                                            <p css={tw`text-neutral-200 font-medium`}>
                                                {profile.name}
                                                {profile.infrastructure_type === 'cloud' && (
                                                    <span css={tw`ml-2 px-2 py-0.5 rounded bg-cyan-600/20 text-cyan-300 text-xs`}>Game Cloud</span>
                                                )}
                                                {profile.infrastructure_type === 'shared' && (
                                                    <span css={tw`ml-2 px-2 py-0.5 rounded bg-purple-600/20 text-purple-300 text-xs`}>On your Cloud</span>
                                                )}
                                            </p>
                                            <p css={tw`text-neutral-500 text-xs`}>
                                                {profile.infrastructure_type === 'cloud'
                                                    ? `${profile.cpu} vCPU · ${Math.round(profile.memory / 1024)} GB RAM · ${profile.disk} GB NVMe — your own VM`
                                                    : profile.infrastructure_type === 'shared'
                                                      ? `${Math.round(profile.game_memory / 1024)} GB on your Game Cloud`
                                                      : `${profile.cpu} vCPU · ${Math.round(profile.memory / 1024)} GB RAM · ${profile.disk} GB NVMe`}
                                            </p>
                                        </div>
                                        <button
                                            disabled={ordering === profile.slug}
                                            onClick={() => order(profile.slug)}
                                            css={tw`px-4 py-2 rounded bg-cyan-600 hover:bg-cyan-500 text-white text-sm font-medium transition-colors duration-150 disabled:opacity-50`}
                                        >
                                            {ordering === profile.slug ? '…' : 'Order'}
                                        </button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </Card>
                ))}
            </div>
            <div css={tw`mt-8 text-center`}>
                <Link to={'/'} css={tw`text-neutral-500 hover:text-neutral-300 text-sm no-underline`}>
                    Back to dashboard
                </Link>
            </div>
        </PageContentBlock>
    );
};
