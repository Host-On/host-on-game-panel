import React from 'react';
import { Route, Switch, useRouteMatch } from 'react-router-dom';
import styled from 'styled-components/macro';
import tw from 'twin.macro';
import LoginContainer from '@/components/auth/LoginContainer';
import ForgotPasswordContainer from '@/components/auth/ForgotPasswordContainer';
import ResetPasswordContainer from '@/components/auth/ResetPasswordContainer';
import LoginCheckpointContainer from '@/components/auth/LoginCheckpointContainer';
import { NotFound } from '@/components/elements/ScreenBlock';
import { useHistory } from 'react-router';

const Backdrop = styled.div`
    ${tw`min-h-screen w-full flex flex-col items-center justify-center relative overflow-hidden`};
    background:
        radial-gradient(1100px 700px at 12% -10%, rgba(34, 211, 238, 0.14), transparent 58%),
        radial-gradient(900px 650px at 105% 105%, rgba(139, 92, 246, 0.16), transparent 55%),
        radial-gradient(800px 500px at 50% 125%, rgba(34, 211, 238, 0.07), transparent 60%),
        #0b0e13;

    &::before {
        content: '';
        ${tw`absolute inset-0 pointer-events-none`};
        background-image: radial-gradient(rgba(148, 163, 184, 0.05) 1px, transparent 1px);
        background-size: 28px 28px;
    }
`;

export default () => {
    const history = useHistory();
    const { path } = useRouteMatch();

    return (
        <Backdrop>
            <div css={tw`relative w-full py-8`}>
                <Switch>
                    <Route path={`${path}/login`} component={LoginContainer} exact />
                    <Route path={`${path}/login/checkpoint`} component={LoginCheckpointContainer} />
                    <Route path={`${path}/password`} component={ForgotPasswordContainer} exact />
                    <Route path={`${path}/password/reset/:token`} component={ResetPasswordContainer} />
                    <Route path={`${path}/checkpoint`} />
                    <Route path={'*'}>
                        <NotFound onBack={() => history.push('/auth/login')} />
                    </Route>
                </Switch>
            </div>
        </Backdrop>
    );
};
