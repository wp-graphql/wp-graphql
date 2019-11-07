import PropTypes from 'prop-types'
import React from 'react'
import styled from '@emotion/styled'
import { useStaticQuery, graphql } from 'gatsby'

const Container = styled.div({
  display: 'flex',
  alignItems: 'center',
  flexShrink: 0,
  fontSize: 18,
  color: 'white',
})

export const StyledLogo = styled.div({
  marginRight: 8,
  height: 36,
  fill: 'currentColor',
})

export default function LogoTitle(props) {
  const { file } = useStaticQuery(graphql`
    {
      file(relativePath: { eq: "icon.png" }) {
        childImageSharp {
          fluid(maxWidth: 100) {
            src
            base64
          }
        }
      }
    }
  `)

  return (
    <Container className={props.className}>
      {!props.noLogo && !!file && (
        <img
          alt="WPGraphQL Logo"
          style={{ marginRight: 10, maxWidth: 50 }}
          src={file.childImageSharp.fluid.src}
        />
      )}
      {props.title}
    </Container>
  )
}

LogoTitle.propTypes = {
  noLogo: PropTypes.bool,
  className: PropTypes.string,
  title: PropTypes.string.isRequired,
}
