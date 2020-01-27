import React from 'react';
import { graphql, useStaticQuery, Link } from 'gatsby'
import { css } from '@emotion/core'
import styled from '@emotion/styled'
import { Icon } from 'antd';

/**
 * This is a styled button using same styles that Github buttons use
 *
 * @type {StyledComponent<JSXInEl["button"], Omit<InnerProps & ExtraProps, ReactClassPropKeys>, object>}
 */
const GithubButton = styled.span`
    position: relative;
    display: inline-block;
    padding: 6px 12px;
    font-size: 14px;
    font-weight: 600;
    line-height: 20px;
    white-space: nowrap;
    vertical-align: middle;
    cursor: pointer;
    -webkit-user-select: none;
    -moz-user-select: none;
    -ms-user-select: none;
    user-select: none;
    background-repeat: repeat-x;
    background-position: -1px -1px;
    background-size: 110% 110%;
    border: 1px solid rgba(27,31,35,.2);
    border-radius: .25em;
    -webkit-appearance: none;
    -moz-appearance: none;
    appearance: none;
    padding: 3px 10px;
    font-size: 12px;
    line-height: 20px;
    color: #24292e;
    background-color: #eff3f6;
    background-image: linear-gradient(-180deg,#fafbfc,#eff3f6 90%);
`;

const ExtensionsList = () => {

    const data = useStaticQuery(graphql`
    {
      allWpGraphQlExtension(sort: {fields: [stargazers___totalCount], order: DESC}) {
        nodes {
          id
          slug
          name
          descriptionHTML
          stargazers {
            totalCount
          }
          readme {
            childMarkdownRemark {
              id
              timeToRead
              rawMarkdownBody
            }
          }
        }
      }
    }
  `);

    if ( ! data || ! data.allWpGraphQlExtension ) {
        return <div>No Extensions...</div>;
    } else {
        const { nodes } = data.allWpGraphQlExtension;
        return (
            <ul css={css`
                margin:0;
                list-style:none;
            `}>
                {nodes && nodes.map(extension => {
                    return (
                        <li
                            key={extension.id}
                            css={css`
                                margin:0;
                            `}
                        >
                            <Link
                                to={extension.slug}
                                css={css`
                                    border-bottom: 1px solid #eaeaea;
                                    padding: 10px 10px 10px 10px;
                                    width: 100%;
                                    display:block;
                                    :hover,:focus {
                                      background: #eaeaea;
                                    }
                                `}
                            >
                                <h2 css={css`
                                    padding-top:10px;
                                `}>
                                    {extension.name}
                                    <GithubButton
                                        css={css`float:right;`}
                                    >
                                        <Icon
                                            css={css`padding-top:4px;`}
                                            type="star"
                                            theme="filled"
                                        /> {extension.stargazers.totalCount}
                                    </GithubButton>
                                </h2>
                                <div
                                    css={css`
                                        color:#666666;
                                    `}
                                    dangerouslySetInnerHTML={{__html:extension.descriptionHTML}}
                                />
                            </Link>
                        </li>
                    );
                })}
            </ul>
        );
    }
};

export default ExtensionsList;
