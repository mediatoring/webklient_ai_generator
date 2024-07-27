const { registerBlockType } = wp.blocks;
const { TextControl, Button, Placeholder } = wp.components;
const { useState } = wp.element;

registerBlockType('article-generator/content-block', {
    title: 'AI Generátor Obsahu',
    icon: 'text',
    category: 'common',
    attributes: {
        content: {
            type: 'string',
            default: '',
        },
    },
    edit: (props) => {
        const { attributes, setAttributes } = props;
        const [topic, setTopic] = useState('');
        const [isLoading, setIsLoading] = useState(false);

        const generateContent = () => {
            setIsLoading(true);
            wp.apiFetch({
                path: '/article-generator/v1/generate',
                method: 'POST',
                data: { topic },
            }).then((response) => {
                setAttributes({ content: response.content });
                setIsLoading(false);
            }).catch((error) => {
                console.error('Chyba při generování obsahu:', error);
                setIsLoading(false);
            });
        };

        return (
            <div>
                {attributes.content ? (
                    <div>
                        <div dangerouslySetInnerHTML={{ __html: attributes.content }} />
                        <Button isPrimary onClick={() => setAttributes({ content: '' })}>
                            Resetovat obsah
                        </Button>
                    </div>
                ) : (
                    <Placeholder
                        icon="text"
                        label="AI Generátor Obsahu"
                        instructions="Zadejte téma a klikněte na tlačítko pro vygenerování obsahu."
                    >
                        <TextControl
                            value={topic}
                            onChange={setTopic}
                            placeholder="Zadejte téma"
                        />
                        <Button
                            isPrimary
                            onClick={generateContent}
                            disabled={isLoading}
                        >
                            {isLoading ? 'Generuji...' : 'Generovat obsah'}
                        </Button>
                    </Placeholder>
                )}
            </div>
        );
    },
    save: (props) => {
        return <div dangerouslySetInnerHTML={{ __html: props.attributes.content }} />;
    },
});
