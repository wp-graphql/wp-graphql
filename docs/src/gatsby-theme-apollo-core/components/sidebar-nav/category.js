import PropTypes from 'prop-types'
import React, { Fragment } from 'react'
import styled from '@emotion/styled'
import { IconArrowDown } from '@apollo/space-kit/icons/IconArrowDown'
import { IconArrowUp } from '@apollo/space-kit/icons/IconArrowUp'
import { Link } from 'gatsby'
import { colors } from 'gatsby-theme-apollo-core/src/utils/colors'
import { smallCaps } from 'gatsby-theme-apollo-core/src/utils/typography'

const iconSize = 14
const headingPadding = 16
const headingStyles = {
  display: 'flex',
  alignItems: 'center',
  width: '100%',
  marginBottom: 0,
  padding: headingPadding,
  paddingLeft: 0,
  border: 0,
  color: colors.text2,
  background: 'none',
  outline: 'none',
  h6: {
    margin: 0,
    fontWeight: 'bold',
    ...smallCaps,
    color: 'inherit',
  },
  svg: {
    display: 'block',
    width: iconSize,
    height: iconSize,
    marginLeft: 'auto',
    fill: 'currentColor',
  },
  '&.active': {
    color: colors.primary,
  },
}

const Container = styled.div(props => ({
  borderTop: !props.first && `1px solid ${colors.divider}`,
  marginTop: props.first && headingPadding / -2,
}))

const StyledButton = styled.button(headingStyles, {
  ':not([disabled])': {
    cursor: 'pointer',
    ':hover': {
      opacity: colors.hoverOpacity,
    },
  },
})

const StyledLink = styled(Link)(headingStyles, {
  textDecoration: 'none',
})

export default function Category(props) {
  const Icon = props.expanded ? IconArrowUp : IconArrowDown
  const contents = (
    <Fragment>
      <h6>{props.title}</h6>
      <Icon
        style={{
          visibility: props.onClick ? 'visible' : 'hidden',
        }}
      />
    </Fragment>
  )

  const className = props.active && 'active'
  return (
    <Container first={props.isFirst}>
      {!props.onClick && props.path ? (
        <StyledLink className={className} to={props.path}>
          {contents}
        </StyledLink>
      ) : (
        <StyledButton
          onClick={props.onClick ? () => props.onClick(props.title) : null}
          aria-label={props.title}
        >
          {contents}
        </StyledButton>
      )}
      {props.expanded && props.children}
    </Container>
  )
}

Category.propTypes = {
  title: PropTypes.string.isRequired,
  path: PropTypes.string,
  expanded: PropTypes.bool.isRequired,
  children: PropTypes.node.isRequired,
  active: PropTypes.bool.isRequired,
  isFirst: PropTypes.bool.isRequired,
  onClick: PropTypes.func,
}
