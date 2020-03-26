import PropTypes from 'prop-types'
import React, { useContext, useEffect, useState } from 'react'
import Slugger from 'github-slugger'
import striptags from 'striptags'
import styled from '@emotion/styled'
import useScroll from 'react-use/lib/useScroll'
import useWindowSize from 'react-use/lib/useWindowSize'
import { MainRefContext, trackEvent } from 'gatsby-theme-apollo-docs/src/utils'
import { colors } from 'gatsby-theme-apollo-core'
import wpgqlColors from '../../utils/colors'

const StyledList = styled.ul({
  marginLeft: 0,
  marginBottom: 48,
  overflow: 'auto',
})

const StyledListItem = styled.li(props => ({
  listStyle: 'none',
  fontSize: '1rem',
  lineHeight: 'inherit',
  color: props.active ? wpgqlColors.primary : colors.primary,
  marginTop: props.newSection ? `1rem` : false,
  a: {
    color: 'inherit',
    textDecoration: 'none',
    paddingLeft: props.depth > 2 ? `${props.depth - 2}rem` : 0,
    display: 'block',
    overflow: 'hidden',
    textOverflow: 'ellipsis',
    ':hover': {
      opacity: colors.hoverOpacity,
    },
  },
}))
function handleHeadingClick(event) {
  trackEvent({
    eventCategory: 'Section Nav',
    eventAction: 'heading click',
    eventLabel: event.target.innerText,
  })
}

export default function SectionNav(props) {
  const mainRef = useContext(MainRefContext)
  const { y } = useScroll(mainRef)
  const { width, height } = useWindowSize()
  const [offsets, setOffsets] = useState([])

  const { contentRef, imagesLoaded } = props
  useEffect(() => {
    const headings = contentRef.current.querySelectorAll('h1, h2')
    setOffsets(
      Array.from(headings)
        .map(heading => {
          const anchor = heading.querySelector('a')
          if (!anchor) {
            return null
          }

          return {
            id: heading.id,
            offset: anchor.offsetTop,
          }
        })
        .filter(Boolean)
    )
  }, [width, height, contentRef, imagesLoaded])

  let activeHeading = null
  const windowOffset = height / 2
  const scrollTop = y + windowOffset
  for (let i = offsets.length - 1; i >= 0; i--) {
    const { id, offset } = offsets[i]
    if (scrollTop >= offset) {
      activeHeading = id
      break
    }
  }

  const slugger = new Slugger()
  let lastDepth = false
  return (
    <StyledList>
      {props.headings.map(({ value, depth }) => {
        const text = striptags(value)
        const slug = slugger.slug(text)

        let newSection = lastDepth > 2 && depth === 2

        lastDepth = depth
        return (
          <StyledListItem
            depth={depth}
            newSection={newSection}
            key={slug}
            active={slug === activeHeading}
          >
            <a href={`#${slug}`} onClick={handleHeadingClick}>
              {text}
            </a>
          </StyledListItem>
        )
      })}
    </StyledList>
  )
}

SectionNav.propTypes = {
  headings: PropTypes.array.isRequired,
  imagesLoaded: PropTypes.bool.isRequired,
  contentRef: PropTypes.object.isRequired,
}
