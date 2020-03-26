import React, { Component } from 'react'
import GraphiQL from 'graphiql'
import { parse, print } from 'graphql'
import uniqueId from 'react-html-id'
import fetch from 'isomorphic-fetch'
import classNames from 'classnames'
import { get, map } from 'lodash'
import v4 from 'uuid/v4'
import Joyride from 'react-joyride'
import ToggleButton from '../ToggleButton'
import 'graphiql/graphiql.css'
import './style.css'

class GraphiQLComponent extends Component {
  state = {
    render: false,
    headers: {},
    selected: {},
  }

  constructor() {
    super()

    uniqueId.enableUniqueIds(this)
  }

  componentDidMount() {
    this.setState({
      render: true,
    })
  }

  fetcher = graphQLParams => {
    const { endpoint, showQueryLog = false, showTracing = false } = this.props

    return fetch(endpoint ? endpoint : 'https://www.wpgraphql.com/graphql', {
      method: 'post',
      headers: {
        'Content-Type': 'application/json',
        ...this.state.headers,
      },
      body: JSON.stringify(graphQLParams),
    })
      .then(response => response.json())
      .then(res => {
        if (!showTracing && res.extensions && res.extensions.tracing) {
          delete res.extensions.tracing
        }

        if (!showQueryLog && res.extensions && res.extensions.queryLog) {
          delete res.extensions.queryLog
        }

        if (res.extensions && Object.keys(res.extensions).length === 0) {
          delete res.extensions
        }

        return res
      })
  }

  showJoyRide = id => {
    const { withDocs, showJoyride } = this.props

    let steps = [
      {
        target: '.' + id + ' .graphiql-container',
        title: 'GraphiQL IDE',
        content:
          'This is the GraphiQL IDE. It allows you to interact with a live WPGraphQL API',
        placement: 'top',
      },
      {
        target: '.' + id + ' .topBar',
        title: 'Toolbar',
        content:
          'This is the GraphiQL Toolbar. It contains buttons to interact with the IDE.',
        placement: 'top',
      },
      {
        target: '.' + id + ' .execute-button',
        title: 'Execute Button',
        content:
          'This is the Execute Button. It will execute the operation (query or mutation) in the query editor.',
        placement: 'top',
      },
      {
        target: '.' + id + ' .query-editor',
        content:
          'This is the Query Editor. You can write your GraphQL Queries and Mutations here.',
        placement: 'left',
      },
      {
        target: '.' + id + ' .result-window',
        content:
          'This is the Result Window. When a query is executed, the results are displayed here.',
        placement: 'right',
      },
    ]

    if (withDocs) {
      const docSteps = [
        {
          target: '.' + id + ' .doc-explorer',
          content: 'This is the documentation explorer.',
          placement: 'top',
        },
        {
          target: '.' + id + ' .search-box',
          content: 'Enter keywords to search the Schema.',
        },
        {
          target: '.' + id + ' .doc-category',
          content:
            'Interact with the Schema by clicking on Types and Fields to discover what is available in the Schema.',
        },
      ]

      if ('docsOnly' === showJoyride) {
        steps = docSteps
      } else {
        steps = [...steps, ...docSteps]
      }
    }

    return <Joyride steps={steps} continuous={true} showProgress={true} />
  }

  setAuthHeader = (auth, key) => {
    const { endpoint } = this.props
    fetch(endpoint ? endpoint : 'https://www.wpgraphql.com/graphql', {
      method: 'post',
      headers: {
        'Content-Type': 'application/json',
      },
      body: JSON.stringify({
        query: `
          mutation ($clientId: String!, $user: String!, $pass: String!) {
            login (input: {
                clientMutationId: $clientId
                username: $user
                password: $pass
            }) {
              authToken
            }
          }
        `,
        variables: {
          clientId: v4(),
          user: auth[0],
          pass: auth[1],
        },
      }),
    })
      .then(response => response.json())
      .then(res => {
        const token = get(res, 'data.login.authToken')
        if (token) {
          this.setState({
            headers: { Authorization: `Bearer ${token}` },
            selected: { [key]: 'green' },
          })
        }
      })
      .catch(err => {
        this.setState({ selected: { [key]: 'red' } })
        console.error(err)
      })

    this.setState({ selected: { [key]: 'yellow' } })
  }

  render() {
    const { render } = this.state
    const {
      query,
      variables,
      withDocs = false,
      showJoyride = false,
      authButtons = {},
    } = this.props
    const formattedQuery = print(parse(query))
    const showDocs = true !== withDocs ? 'without-docs' : null
    const uniqid = this.nextUniqueId()

    return (
      <React.Fragment>
        {map(authButtons, (auth, key) => (
          <ToggleButton
            onClick={() => this.setAuthHeader(auth, key)}
            state={this.state.selected[key]}
            key={key}
            label={`Login As ${key}`}
          />
        ))}
        {render ? (
          <div
            className={classNames('graphiql-wrapper', showDocs, uniqid)}
            style={{
              height: '500px',
              width: '100%',
              margin: '15px 0',
              padding: '0',
            }}
          >
            {showJoyride ? this.showJoyRide(uniqid) : null}
            <GraphiQL
              fetcher={this.fetcher}
              query={formattedQuery}
              variables={variables ? JSON.stringify(variables, null, 2) : null}
              extensions={{
                queryLog: true,
                tracing: false,
              }}
            />
          </div>
        ) : (
          'GraphiQL Loading...'
        )}
      </React.Fragment>
    )
  }
}

export default GraphiQLComponent
