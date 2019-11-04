import PropTypes from 'prop-types'
import React from 'react'
import { Helmet } from 'react-helmet'
// import { withPrefix } from 'gatsby'

export default function SEO(props) {
  const {
    title,
    description,
    siteName,
    twitterHandle,
    // baseUrl
  } = props
  //   const imagePath = withPrefix('/' + props.image)
  return (
    <Helmet>
      {/* <link rel="icon" href="https://apollographql.com/favicon.ico" /> */}
      <meta property="og:title" content={title} />
      <meta property="og:site_name" content={siteName} />
      <meta property="og:description" content={description} />
      {/* <meta property="og:image" content={imagePath} /> */}
      {/* <meta name="twitter:card" content="summary_large_image" /> */}
      <meta name="twitter:card" content="summary" />
      <meta name="twitter:title" content={title} />
      <meta name="twitter:description" content={description} />
      {/* <meta name="twitter:image" content={baseUrl + imagePath} /> */}
      {twitterHandle && (
        <meta name="twitter:site" content={`@${twitterHandle}`} />
      )}
    </Helmet>
  )
}

SEO.propTypes = {
  title: PropTypes.string.isRequired,
  description: PropTypes.string.isRequired,
  siteName: PropTypes.string.isRequired,
  twitterHandle: PropTypes.string,
  baseUrl: PropTypes.string.isRequired,
  image: PropTypes.string.isRequired,
}
