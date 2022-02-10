import {
    createNewPost, getEditedPostContent, insertBlock,
} from '@wordpress/e2e-test-utils'

describe( 'Buttons', () => {

    beforeEach( async () => {
        await createNewPost()
    } );

    it ('has focus on button content', async () => {
        await insertBlock( 'Buttons' );
        await page.keyboard.type( 'Content' );
        expect( await getEditedPostContent() ).toMatchSnapshot();
    })

})
